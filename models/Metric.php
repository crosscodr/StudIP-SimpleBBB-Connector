<?php
/**
 * @author  David Siegfried <david.siegfried@uni-vechta.de>
 * @license GPL2 or any later version
 */

namespace Vec\BBB;

use SimpleORMap;
use DateTime;
use DBManager;
use SimpleORMapCollection;

class Metric extends SimpleORMap
{
    const BBB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected static function configure($config = [])
    {
        $config['db_table'] = 'bigbluebutton_metrics';

        $config['belongs_to']['server'] = [
            'class_name'        => 'Vec\\BBB\\Server',
            'assoc_foreign_key' => 'server_id',
        ];
        parent::configure($config);
    }

    public static function getCollectedYears()
    {
        return DBManager::get()->fetchColumn('SELECT DISTINCT YEAR(`start_time`) FROM `bigbluebutton_metrics`');
    }

    public static function collect()
    {
        $servers = SimpleORMapCollection::createFromArray(
            Server::findBySQL('1')
        )->orderBy('name');
        foreach ($servers as $server) {
            $result = ['server' => $server];

            $bbb = new BigBlueButton(
                $server->secret,
                rtrim($server->url, 'api')
            );

            $response = $bbb->getMeetings();
            $meetings = $response->getRawXml()->meetings->meeting;

            if (!empty($meetings)) {
                $result['complete_ounter'] = count($meetings);

                foreach ($meetings as $meeting) {
                    $course     = null;
                    $start_time = (new DateTime())
                        ->setTimestamp((int)$meeting->startTime / 1000)
                        ->format(self::BBB_DATETIME_FORMAT);
                    $end_time   = null;
                    if ((int)$meeting->endTime) {
                        $end_time = (new DateTime())
                            ->setTimestamp((int)$meeting->endTime / 1000)
                            ->format(self::BBB_DATETIME_FORMAT);
                    }

                    $data   =
                        [
                            'server_id'               => $server->id,
                            'meeting_id'              => (string)$meeting->meetingID,
                            'meeting_name'            => (string)$meeting->meetingName,
                            'participant_count'       => (string)$meeting->participantCount,
                            'video_count'             => (int)$meeting->videoCount,
                            'listener_count'          => (int)$meeting->listenerCount,
                            'voice_participant_count' => (int)$meeting->voiceParticipantCount,
                            'moderator_count'         => (int)$meeting->moderatorCount,
                            'is_break_out'            => (string)$meeting->isBreakout === "true" ? 1 : 0,
                            'start_time'              => $start_time,
                            'end_time'                => $end_time,
                        ];
                    $metric = self::findOneBySQL('meeting_id = ? AND start_time = ?', [$data['meeting_id'], $data['start_time']]);
                    if (!$metric) {
                        $metric = self::build($data);
                    } else {
                        $metric->setData($data);
                    }
                    $metric->store();
                }
            }
        }
    }

    public static function getStatistics($filter = '')
    {
        $sql = 'SELECT 
                COUNT(*) as "Anzahl Konferenzen",
                SUM(participant_count) as "Anzahl TeilnehmerInnen",
                SUM(video_count) as "Anzahl Video",
                SUM(listener_count) as "Anzahl ZuhörerInnen",
                SUM(voice_participant_count) as "Anzahl Audio",
                SUM(moderator_count) as "Anzahl ModeratorenInnen",
                SUM(is_break_out) as "Anzahl BreakOutRäume"
                FROM `bigbluebutton_metrics`';

        $attributes = [];
        if ($filter === 'current_month') {
            $begin = (new \DateTime('first day of this month 00:00:00'))->format(self::BBB_DATETIME_FORMAT);
            $end   = (new \DateTime('first day of next month 00:00:00'))->format(self::BBB_DATETIME_FORMAT);

            $sql                       .= ' WHERE start_time BETWEEN :start_time AND :end_time';
            $attributes[':start_time'] = $begin;
            $attributes[':end_time']   = $end;
        }

        return \DBManager::get()->fetchOne($sql, $attributes);
    }
}