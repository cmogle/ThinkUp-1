<?php
/*
 Plugin Name: Bio tracker
 Description: Which of your friends have changed their profile's bio information, and how.
 When: Always
 */
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/insights/biotracker.php
 *
 * Copyright (c) 2014-2015 Chris Moyer
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2014-2015 Chris Moyer
 * @author Chris Moyer <chris [at] inarow [dot] net>
 */
class BioTrackerInsight extends InsightPluginParent implements InsightPlugin {
    /**
     * Slug for this insight
     **/
    var $slug = 'bio_tracker';

    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        parent::generateInsight($instance, $user, $last_week_of_posts, $number_days);
        $this->logger->logInfo("Begin generating insight", __METHOD__.','.__LINE__);

        if ($instance->network == 'twitter' && $this->shouldGenerateInsight($this->slug, $instance)) {
            $user_versions_dao = DAOFactory::getDAO('UserVersionsDAO');
            $versions = $user_versions_dao->getRecentFriendsVersions($user, 7, array('description'));
            //$this->logger->logInfo(Utils::varDumpToString($versions), __METHOD__.','.__LINE__);
            $changes = array();
            $examined_users = array();
            foreach ($versions as $change) {
                $user_key = intval($change['user_key']);
                if (!in_array($user_key, $examined_users)) {
                    $examined_users[] = $user_key;
                    $last_description = $user_versions_dao->getVersionBeforeDay($user_key,date('Y-m-d'),'description');
                    if ($last_description) {
                        $user_dao = DAOFactory::getDAO('UserDAO');
                        $user = $user_dao->getDetailsByUserKey($user_key);
                        if ($user
                            && Utils::stripURLsOutOfText($user->description)
                            !== Utils::stripURLsOutOfText($last_description['field_value'])) {
                            $changes[] = array(
                                'user' => $user,
                                'field_name' => 'description',
                                'field_description' => 'bio',
                                'before' => $last_description['field_value'],
                                'after' => $user->description
                            );
                        }
                    }
                }
            }
            $this->logger->logInfo("Got ".count($changes)." changes", __METHOD__.','.__LINE__);
            if (count($changes) > 0) {
                $changes = array_slice($changes, 0, 10);
                $insight = new Insight();
                $insight->instance_id = $instance->id;
                $insight->slug = $this->slug;
                $insight->date = $this->insight_date;
                $insight->filename = basename(__FILE__, ".php");
                $insight->emphasis = Insight::EMPHASIS_MED;
                $insight->related_data = array('changes' => $changes);
                $insight->text = $this->getText($changes, $instance);
                $insight->headline = $this->getHeadline($changes, $instance);
                if (count($changes) == 1) {
                    $insight->header_image = $changes[0]["user"]->avatar;
                }
                $this->insight_dao->insertInsight($insight);
            }
        }

        $this->logger->logInfo("Done generating insight", __METHOD__.','.__LINE__);
    }

    private function getText($changes, $instance) {
        $network = ucfirst($instance->network);
        $text_options = array(
            "Spot the difference?",
            "Even small changes can be big news."
        );
        if (count($changes) == 1) {
            $username = ($changes[0]['user']->network == 'twitter' ? '@' : '') . $changes[0]['user']->username;
            $base = "$username has an updated $network profile.";
            $they = $username;
        } else {
            $base = count($changes) . " of ".$this->username."'s friends changed their $network description.";
            $text_options[] = "They might appreciate that someone noticed.";
        }
        return $base . ' '. $this->getVariableCopy($text_options);
    }

    private function getHeadline($changes, $instance) {
        $network = ucfirst($instance->network);
        $username = ($changes[0]['user']->network == 'twitter' ? '@' : '') . $changes[0]['user']->username;
        if (count($changes) == 1) {
            $base = $this->getVariableCopy(array(
                "Something's different about %user1",
                "%user1 changes it up",
                "%user1 makes an adjustment",
                "%user1 tries something new",
                "What's new with %user1"
            ), array('user1' => $username));
        } else {
            $second_username = ($changes[0]['user']->network == 'twitter' ? '@' : '') . $changes[1]['user']->username;
            if (count($changes) > 2) {
                $total_more = count($changes) - 2;
                $base = $username.", ".$second_username.", and ".$total_more." other".
                (($total_more == 1)?"":"s")." changed their profiles";
            } else {
                $base = $username." and ".$second_username." changed their profiles";
            }
        }
        return $base;
    }

}

$insights_plugin_registrar = PluginRegistrarInsights::getInstance();
$insights_plugin_registrar->registerInsightPlugin('BioTrackerInsight');
