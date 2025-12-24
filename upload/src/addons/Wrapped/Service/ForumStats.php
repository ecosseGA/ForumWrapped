<?php

namespace IdleChatter\Wrapped\Service;

use XF\Service\AbstractService;

class ForumStats extends AbstractService
{
    protected $year;
    protected $startDate;
    protected $endDate;
    
    public function __construct(\XF\App $app, $year)
    {
        parent::__construct($app);
        $this->year = $year;
        
        // Calculate date range for the year
        $this->startDate = strtotime($year . '-01-01 00:00:00');
        $this->endDate = strtotime($year . '-12-31 23:59:59');
    }
    
    public function getStats()
    {
        $stats = [
            'overview' => $this->getOverview(),
            'growth' => $this->getGrowth(),
            'topPosters' => $this->getTopPosters(),
            'topThreadStarters' => $this->getTopThreadStarters(),
            'topReactedMembers' => $this->getTopReactedMembers(),
            'risingStars' => $this->getRisingStars(),
            'topThreads' => $this->getTopThreads(),
            'mostViewed' => $this->getMostViewedThreads(),
            'mostReactedPosts' => $this->getMostReactedPosts(),
            'longestThread' => $this->getLongestThread(),
            'forumActivity' => $this->getForumActivity(),
            'monthlyActivity' => $this->getMonthlyActivity(),
            'hourlyActivity' => $this->getHourlyActivity(),
            'weekendVsWeekday' => $this->getWeekendVsWeekday(),
            'mostEngagedThread' => $this->getMostEngagedThread(),
            'mostActiveStreak' => $this->getMostActiveStreak(),
            'timeOfDay' => $this->getTimeOfDayBreakdown(),
            'highlights' => $this->getHighlights()
        ];
        
        return $stats;
    }
    
    protected function getOverview()
    {
        $db = $this->db();
        
        // Total posts
        $totalPosts = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        // Total threads
        $totalThreads = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE post_date >= ? AND post_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        // New members
        $newMembers = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_user
            WHERE register_date >= ? AND register_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        // Total reactions given
        $totalReactions = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE reaction_date >= ? AND reaction_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        // Active members (posted at least once)
        $activeMembers = $db->fetchOne("
            SELECT COUNT(DISTINCT user_id)
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        return [
            'totalPosts' => (int)$totalPosts,
            'totalThreads' => (int)$totalThreads,
            'newMembers' => (int)$newMembers,
            'totalReactions' => (int)$totalReactions,
            'activeMembers' => (int)$activeMembers
        ];
    }
    
    protected function getGrowth()
    {
        $db = $this->db();
        
        // Previous year dates
        $prevYear = $this->year - 1;
        $prevStartDate = strtotime($prevYear . '-01-01 00:00:00');
        $prevEndDate = strtotime($prevYear . '-12-31 23:59:59');
        
        // Previous year posts
        $prevPosts = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
        ", [$prevStartDate, $prevEndDate]);
        
        // Previous year threads
        $prevThreads = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE post_date >= ? AND post_date <= ?
        ", [$prevStartDate, $prevEndDate]);
        
        // Previous year members
        $prevMembers = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_user
            WHERE register_date >= ? AND register_date <= ?
        ", [$prevStartDate, $prevEndDate]);
        
        // Current year stats
        $overview = $this->getOverview();
        
        // Calculate growth percentages
        $postGrowth = $prevPosts > 0 ? round((($overview['totalPosts'] - $prevPosts) / $prevPosts) * 100, 1) : 0;
        $threadGrowth = $prevThreads > 0 ? round((($overview['totalThreads'] - $prevThreads) / $prevThreads) * 100, 1) : 0;
        $memberGrowth = $prevMembers > 0 ? round((($overview['newMembers'] - $prevMembers) / $prevMembers) * 100, 1) : 0;
        
        return [
            'postGrowth' => $postGrowth,
            'threadGrowth' => $threadGrowth,
            'memberGrowth' => $memberGrowth,
            'prevPosts' => (int)$prevPosts,
            'prevThreads' => (int)$prevThreads,
            'prevMembers' => (int)$prevMembers
        ];
    }
    
    protected function getTopPosters()
    {
        $db = $this->db();
        
        $topPosters = $db->fetchAll("
            SELECT p.user_id, u.username, COUNT(*) as post_count
            FROM xf_post p
            INNER JOIN xf_user u ON p.user_id = u.user_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY p.user_id
            ORDER BY post_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($topPosters as &$poster) {
            $poster['rank'] = $rank++;
            $poster['user'] = $this->em()->find('XF:User', $poster['user_id']);
        }
        
        return $topPosters;
    }
    
    protected function getTopThreadStarters()
    {
        $db = $this->db();
        
        $topStarters = $db->fetchAll("
            SELECT t.user_id, u.username, COUNT(*) as thread_count
            FROM xf_thread t
            INNER JOIN xf_user u ON t.user_id = u.user_id
            WHERE t.post_date >= ? AND t.post_date <= ?
            GROUP BY t.user_id
            ORDER BY thread_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($topStarters as &$starter) {
            $starter['rank'] = $rank++;
            $starter['user'] = $this->em()->find('XF:User', $starter['user_id']);
        }
        
        return $topStarters;
    }
    
    protected function getTopReactedMembers()
    {
        $db = $this->db();
        
        $topReacted = $db->fetchAll("
            SELECT rc.content_user_id as user_id, u.username, COUNT(*) as reaction_count
            FROM xf_reaction_content rc
            INNER JOIN xf_user u ON rc.content_user_id = u.user_id
            WHERE rc.reaction_date >= ? 
            AND rc.reaction_date <= ?
            AND rc.content_type = 'post'
            GROUP BY rc.content_user_id
            ORDER BY reaction_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($topReacted as &$member) {
            $member['rank'] = $rank++;
            $member['user'] = $this->em()->find('XF:User', $member['user_id']);
        }
        
        return $topReacted;
    }
    
    protected function getRisingStars()
    {
        $db = $this->db();
        
        // New members who joined this year with high post counts
        $risingStars = $db->fetchAll("
            SELECT u.user_id, u.username, COUNT(p.post_id) as post_count
            FROM xf_user u
            INNER JOIN xf_post p ON u.user_id = p.user_id
            WHERE u.register_date >= ?
            AND u.register_date <= ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            GROUP BY u.user_id
            HAVING post_count >= 10
            ORDER BY post_count DESC
            LIMIT 5
        ", [$this->startDate, $this->endDate, $this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($risingStars as &$star) {
            $star['rank'] = $rank++;
            $star['user'] = $this->em()->find('XF:User', $star['user_id']);
        }
        
        return $risingStars;
    }
    
    protected function getTopThreads()
    {
        $db = $this->db();
        
        // Get threads with most replies DURING the selected year
        $topThreads = $db->fetchAll("
            SELECT 
                t.thread_id,
                t.title,
                t.user_id,
                t.view_count,
                COUNT(p.post_id) - 1 as year_replies
            FROM xf_thread t
            INNER JOIN xf_post p ON t.thread_id = p.thread_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY t.thread_id
            HAVING year_replies > 0
            ORDER BY year_replies DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($topThreads as &$thread) {
            $thread['rank'] = $rank++;
            $thread['reply_count'] = $thread['year_replies'];
            $thread['thread'] = $this->em()->find('XF:Thread', $thread['thread_id']);
        }
        
        return $topThreads;
    }
    
    protected function getMostViewedThreads()
    {
        $db = $this->db();
        
        // Get threads that had activity during the selected year, ordered by total view count
        $mostViewed = $db->fetchAll("
            SELECT DISTINCT
                t.thread_id,
                t.title,
                t.user_id,
                t.view_count,
                t.reply_count
            FROM xf_thread t
            INNER JOIN xf_post p ON t.thread_id = p.thread_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY t.thread_id
            ORDER BY t.view_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($mostViewed as &$thread) {
            $thread['rank'] = $rank++;
            $thread['thread'] = $this->em()->find('XF:Thread', $thread['thread_id']);
        }
        
        return $mostViewed;
    }
    
    protected function getMostReactedPosts()
    {
        $db = $this->db();
        
        $mostReacted = $db->fetchAll("
            SELECT p.post_id, p.user_id, p.message, t.title as thread_title, 
                   COUNT(rc.reaction_content_id) as reaction_count
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            LEFT JOIN xf_reaction_content rc ON rc.content_id = p.post_id AND rc.content_type = 'post'
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY p.post_id
            ORDER BY reaction_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        $rank = 1;
        foreach ($mostReacted as &$post) {
            $post['rank'] = $rank++;
            $post['post'] = $this->em()->find('XF:Post', $post['post_id']);
            $post['snippet'] = \XF::app()->stringFormatter()->snippetString(strip_tags($post['message']), 150);
        }
        
        return $mostReacted;
    }
    
    protected function getLongestThread()
    {
        $db = $this->db();
        
        $longest = $db->fetchRow("
            SELECT thread_id, title, reply_count, view_count
            FROM xf_thread
            WHERE post_date >= ? AND post_date <= ?
            ORDER BY reply_count DESC
            LIMIT 1
        ", [$this->startDate, $this->endDate]);
        
        if ($longest) {
            $longest['thread'] = $this->em()->find('XF:Thread', $longest['thread_id']);
        }
        
        return $longest;
    }
    
    protected function getForumActivity()
    {
        $db = $this->db();
        
        $forumActivity = $db->fetchAll("
            SELECT n.node_id, n.title, COUNT(p.post_id) as post_count
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            INNER JOIN xf_node n ON t.node_id = n.node_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY n.node_id
            ORDER BY post_count DESC
            LIMIT 10
        ", [$this->startDate, $this->endDate]);
        
        return $forumActivity;
    }
    
    protected function getMonthlyActivity()
    {
        $db = $this->db();
        
        $monthly = $db->fetchPairs("
            SELECT 
                MONTH(FROM_UNIXTIME(post_date)) as month,
                COUNT(*) as count
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
            GROUP BY month
            ORDER BY month
        ", [$this->startDate, $this->endDate]);
        
        // Fill in missing months with 0
        $monthlyData = [];
        $maxCount = 0;
        for ($i = 1; $i <= 12; $i++) {
            $count = isset($monthly[$i]) ? (int)$monthly[$i] : 0;
            $monthlyData[] = [
                'month' => $i,
                'monthName' => date('F', mktime(0, 0, 0, $i, 1)),
                'monthShort' => date('M', mktime(0, 0, 0, $i, 1)),
                'count' => $count,
                'height' => 0 // Will calculate after we know max
            ];
            $maxCount = max($maxCount, $count);
        }
        
        // Calculate heights and find peak month
        $peakMonth = null;
        $peakCount = 0;
        foreach ($monthlyData as &$data) {
            $data['height'] = $maxCount > 0 ? round(($data['count'] / $maxCount) * 100) : 0;
            $data['isPeak'] = false;
            if ($data['count'] > $peakCount) {
                $peakCount = $data['count'];
                $peakMonth = $data['monthName'];
            }
        }
        
        // Mark peak month
        foreach ($monthlyData as &$data) {
            if ($data['count'] == $peakCount) {
                $data['isPeak'] = true;
            }
        }
        
        return [
            'chartData' => $monthlyData,
            'peakMonth' => $peakMonth,
            'peakMonthShort' => date('M', mktime(0, 0, 0, array_search($peakMonth, array_column($monthlyData, 'monthName')) + 1, 1)),
            'peakCount' => $peakCount
        ];
    }
    
    protected function getHourlyActivity()
    {
        $db = $this->db();
        
        $hourly = $db->fetchPairs("
            SELECT 
                HOUR(FROM_UNIXTIME(post_date)) as hour,
                COUNT(*) as count
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
            GROUP BY hour
            ORDER BY hour
        ", [$this->startDate, $this->endDate]);
        
        // Fill in all 24 hours
        $hourlyData = [];
        $maxCount = 0;
        for ($i = 0; $i < 24; $i++) {
            $count = isset($hourly[$i]) ? (int)$hourly[$i] : 0;
            $hourlyData[] = [
                'hour' => $i,
                'displayHour' => date('ga', mktime($i, 0)),
                'count' => $count,
                'height' => 0
            ];
            $maxCount = max($maxCount, $count);
        }
        
        // Calculate heights and find peak
        $peakHour = 0;
        $peakCount = 0;
        foreach ($hourlyData as &$data) {
            $data['height'] = $maxCount > 0 ? round(($data['count'] / $maxCount) * 100) : 0;
            $data['isPeak'] = false;
            if ($data['count'] > $peakCount) {
                $peakCount = $data['count'];
                $peakHour = $data['hour'];
            }
        }
        
        // Mark peak hour
        foreach ($hourlyData as &$data) {
            if ($data['count'] == $peakCount) {
                $data['isPeak'] = true;
            }
        }
        
        // Determine peak time range
        if ($peakHour >= 0 && $peakHour < 6) {
            $peakTimeRange = 'Night Owl Hours (12am-6am)';
        } elseif ($peakHour >= 6 && $peakHour < 12) {
            $peakTimeRange = 'Morning Hours (6am-12pm)';
        } elseif ($peakHour >= 12 && $peakHour < 18) {
            $peakTimeRange = 'Afternoon Hours (12pm-6pm)';
        } else {
            $peakTimeRange = 'Evening Hours (6pm-12am)';
        }
        
        return [
            'chartData' => $hourlyData,
            'peakHour' => $hourlyData[$peakHour]['displayHour'],
            'peakTimeRange' => $peakTimeRange,
            'peakCount' => $peakCount
        ];
    }
    
    protected function getWeekendVsWeekday()
    {
        $db = $this->db();
        
        $dayData = $db->fetchPairs("
            SELECT 
                DAYOFWEEK(FROM_UNIXTIME(post_date)) as day,
                COUNT(*) as count
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
            GROUP BY day
        ", [$this->startDate, $this->endDate]);
        
        // Weekend: 1 (Sunday), 7 (Saturday)
        // Weekday: 2-6 (Monday-Friday)
        $weekendCount = (isset($dayData[1]) ? (int)$dayData[1] : 0) + (isset($dayData[7]) ? (int)$dayData[7] : 0);
        $weekdayCount = 0;
        for ($i = 2; $i <= 6; $i++) {
            $weekdayCount += isset($dayData[$i]) ? (int)$dayData[$i] : 0;
        }
        
        $total = $weekendCount + $weekdayCount;
        $weekendPercent = $total > 0 ? round(($weekendCount / $total) * 100, 1) : 0;
        $weekdayPercent = $total > 0 ? round(($weekdayCount / $total) * 100, 1) : 0;
        
        // Determine badge
        if ($weekendPercent > 55) {
            $badge = 'Weekend Warriors';
        } elseif ($weekdayPercent > 60) {
            $badge = 'Weekday Grinders';
        } else {
            $badge = 'Balanced Community';
        }
        
        return [
            'weekendCount' => $weekendCount,
            'weekdayCount' => $weekdayCount,
            'weekendPercent' => $weekendPercent,
            'weekdayPercent' => $weekdayPercent,
            'badge' => $badge
        ];
    }
    
    protected function getMostEngagedThread()
    {
        $db = $this->db();
        
        // Find thread with most unique participants
        $thread = $db->fetchRow("
            SELECT 
                t.thread_id,
                t.title,
                t.reply_count,
                t.view_count,
                COUNT(DISTINCT p.user_id) as unique_participants,
                (SELECT COUNT(*) FROM xf_reaction_content rc 
                 WHERE rc.content_type = 'post' 
                 AND rc.content_id IN (SELECT post_id FROM xf_post WHERE thread_id = t.thread_id)) as reaction_count
            FROM xf_thread t
            INNER JOIN xf_post p ON t.thread_id = p.thread_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            GROUP BY t.thread_id
            ORDER BY unique_participants DESC
            LIMIT 1
        ", [$this->startDate, $this->endDate]);
        
        if (!$thread) {
            return null;
        }
        
        return [
            'thread_id' => $thread['thread_id'],
            'title' => $thread['title'],
            'unique_participants' => $thread['unique_participants'],
            'reply_count' => $thread['reply_count'],
            'view_count' => $thread['view_count'],
            'reaction_count' => $thread['reaction_count']
        ];
    }
    
    protected function getMostActiveStreak()
    {
        $db = $this->db();
        
        // Get all posts grouped by user and date (excluding usergroup 11)
        $posts = $db->fetchAll("
            SELECT 
                p.user_id,
                FROM_UNIXTIME(p.post_date, '%Y-%m-%d') as post_date
            FROM xf_post p
            INNER JOIN xf_user u ON p.user_id = u.user_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            AND u.user_group_id != 11
            AND (u.secondary_group_ids IS NULL OR FIND_IN_SET(11, u.secondary_group_ids) = 0)
            GROUP BY p.user_id, FROM_UNIXTIME(p.post_date, '%Y-%m-%d')
            ORDER BY p.user_id, post_date
        ", [$this->startDate, $this->endDate]);
        
        if (!$posts) {
            return null;
        }
        
        // Calculate streaks
        $longestStreak = 0;
        $longestStreakUser = null;
        $longestStreakStart = null;
        $longestStreakEnd = null;
        
        $currentUserId = null;
        $currentStreak = 0;
        $currentStreakStart = null;
        $lastDate = null;
        
        foreach ($posts as $post) {
            $userId = $post['user_id'];
            $postDate = strtotime($post['post_date']);
            
            // New user, reset streak
            if ($userId !== $currentUserId) {
                $currentUserId = $userId;
                $currentStreak = 1;
                $currentStreakStart = $postDate;
                $lastDate = $postDate;
                continue;
            }
            
            // Check if consecutive day
            $daysDiff = floor(($postDate - $lastDate) / 86400);
            
            if ($daysDiff == 1) {
                // Consecutive day
                $currentStreak++;
            } else if ($daysDiff > 1) {
                // Streak broken, check if it was the longest
                if ($currentStreak > $longestStreak) {
                    $longestStreak = $currentStreak;
                    $longestStreakUser = $currentUserId;
                    $longestStreakStart = $currentStreakStart;
                    $longestStreakEnd = $lastDate;
                }
                
                // Start new streak
                $currentStreak = 1;
                $currentStreakStart = $postDate;
            }
            
            $lastDate = $postDate;
        }
        
        // Check final streak
        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
            $longestStreakUser = $currentUserId;
            $longestStreakStart = $currentStreakStart;
            $longestStreakEnd = $lastDate;
        }
        
        if (!$longestStreakUser || $longestStreak < 2) {
            return null;
        }
        
        // Get user entity (for avatar)
        $user = \XF::em()->find('XF:User', $longestStreakUser);
        
        if (!$user) {
            return null;
        }
        
        return [
            'user' => $user,
            'streak_days' => $longestStreak,
            'start_date' => $longestStreakStart,
            'end_date' => $longestStreakEnd
        ];
    }
    
    protected function getTimeOfDayBreakdown()
    {
        $db = $this->db();
        
        // Get posts by hour
        $hourly = $db->fetchAll("
            SELECT 
                HOUR(FROM_UNIXTIME(post_date)) as hour,
                COUNT(*) as count
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
            GROUP BY hour
        ", [$this->startDate, $this->endDate]);
        
        // Initialize counters
        $morning = 0;    // 5am - 12pm
        $afternoon = 0;  // 12pm - 6pm
        $evening = 0;    // 6pm - 12am
        $night = 0;      // 12am - 5am
        $total = 0;
        
        foreach ($hourly as $row) {
            $hour = (int)$row['hour'];
            $count = (int)$row['count'];
            $total += $count;
            
            if ($hour >= 5 && $hour < 12) {
                $morning += $count;
            } elseif ($hour >= 12 && $hour < 18) {
                $afternoon += $count;
            } elseif ($hour >= 18 && $hour < 24) {
                $evening += $count;
            } else {
                $night += $count;
            }
        }
        
        if ($total == 0) {
            return null;
        }
        
        // Calculate percentages
        $morningPercent = round(($morning / $total) * 100, 1);
        $afternoonPercent = round(($afternoon / $total) * 100, 1);
        $eveningPercent = round(($evening / $total) * 100, 1);
        $nightPercent = round(($night / $total) * 100, 1);
        
        // Determine badge
        $max = max($morning, $afternoon, $evening, $night);
        if ($max == $morning) {
            $badge = 'Early Birds';
            $badgeEmoji = '🌅';
        } elseif ($max == $afternoon) {
            $badge = 'Afternoon Enthusiasts';
            $badgeEmoji = '☀️';
        } elseif ($max == $evening) {
            $badge = 'Evening Explorers';
            $badgeEmoji = '🌆';
        } else {
            $badge = 'Night Owls';
            $badgeEmoji = '🦉';
        }
        
        return [
            'morning' => $morning,
            'morningPercent' => $morningPercent,
            'afternoon' => $afternoon,
            'afternoonPercent' => $afternoonPercent,
            'evening' => $evening,
            'eveningPercent' => $eveningPercent,
            'night' => $night,
            'nightPercent' => $nightPercent,
            'badge' => $badge,
            'badgeEmoji' => $badgeEmoji,
            'total' => $total
        ];
    }
    
    protected function getHighlights()
    {
        $db = $this->db();
        
        // First post of the year
        $firstPost = $db->fetchRow("
            SELECT p.post_id, p.user_id, p.post_date, p.message, p.thread_id, t.title as thread_title, u.username
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            INNER JOIN xf_user u ON p.user_id = u.user_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            ORDER BY p.post_date ASC
            LIMIT 1
        ", [$this->startDate, $this->endDate]);
        
        // Last post of the year
        $lastPost = $db->fetchRow("
            SELECT p.post_id, p.user_id, p.post_date, p.message, p.thread_id, t.title as thread_title, u.username
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            INNER JOIN xf_user u ON p.user_id = u.user_id
            WHERE p.post_date >= ? AND p.post_date <= ?
            ORDER BY p.post_date DESC
            LIMIT 1
        ", [$this->startDate, $this->endDate]);
        
        // Busiest day
        $busiestDay = $db->fetchRow("
            SELECT 
                DATE(FROM_UNIXTIME(post_date)) as date,
                COUNT(*) as post_count
            FROM xf_post
            WHERE post_date >= ? AND post_date <= ?
            GROUP BY date
            ORDER BY post_count DESC
            LIMIT 1
        ", [$this->startDate, $this->endDate]);
        
        return [
            'firstPost' => $firstPost,
            'lastPost' => $lastPost,
            'busiestDay' => $busiestDay
        ];
    }
}
