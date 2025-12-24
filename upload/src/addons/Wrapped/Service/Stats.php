<?php

namespace IdleChatter\Wrapped\Service;

use XF\Service\AbstractService;

class Stats extends AbstractService
{
    protected $user;
    protected $year;
    protected $startDate;
    protected $endDate;
    
    public function __construct(\XF\App $app, \XF\Entity\User $user, $year)
    {
        parent::__construct($app);
        $this->user = $user;
        $this->year = $year;
        
        // Calculate date range for the year
        $this->startDate = strtotime($year . '-01-01 00:00:00');
        $this->endDate = strtotime($year . '-12-31 23:59:59');
    }
    
    public function getStats()
    {
        $stats = [
            'totals' => $this->getTotals(),
            'yearOverYear' => $this->getYearOverYear(),
            'monthly' => $this->getMonthlyActivity(),
            'weekday' => $this->getWeekdayActivity(),
            'hourly' => $this->getHourlyActivity(),
            'style' => $this->getPostingStyle(),
            'forums' => $this->getTopForums(),
            'threads' => $this->getTopThreads(),
            'interactions' => $this->getTopInteractions(),
            'reactions' => $this->getReactionStats(),
            'bookends' => $this->getBookends(),
            'longestPost' => $this->getLongestPost(),
            'rank' => $this->getCommunityRank(),
            'weekendVsWeekday' => $this->getWeekendVsWeekday(),
            'threadStarter' => $this->getThreadStarterStats(),
            'topPost' => $this->getTopPost()
        ];
        
        return $stats;
    }
    
    protected function getTotals()
    {
        $db = $this->db();
        
        // Total posts
        $posts = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Total threads
        $threads = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Reactions received
        $reactionsReceived = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE content_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Reactions given
        $reactionsGiven = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE reaction_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Total words written
        $totalWords = $db->fetchOne("
            SELECT SUM(LENGTH(message) - LENGTH(REPLACE(message, ' ', '')) + 1)
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        return [
            'posts' => (int)$posts,
            'threads' => (int)$threads,
            'reactionsReceived' => (int)$reactionsReceived,
            'reactionsGiven' => (int)$reactionsGiven,
            'totalWords' => (int)$totalWords,
            'avgWordsPerPost' => $posts > 0 ? round($totalWords / $posts) : 0,
            'reactionsPerPost' => $posts > 0 ? round($reactionsReceived / $posts, 2) : 0
        ];
    }
    
    protected function getYearOverYear()
    {
        $db = $this->db();
        $prevYear = $this->year - 1;
        $prevStartDate = strtotime($prevYear . '-01-01 00:00:00');
        $prevEndDate = strtotime($prevYear . '-12-31 23:59:59');
        
        // Previous year posts
        $prevPosts = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $prevStartDate, $prevEndDate]);
        
        // Previous year threads
        $prevThreads = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $prevStartDate, $prevEndDate]);
        
        // Previous year reactions
        $prevReactions = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE content_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
        ", [$this->user->user_id, $prevStartDate, $prevEndDate]);
        
        return [
            'prevPosts' => (int)$prevPosts,
            'prevThreads' => (int)$prevThreads,
            'prevReactions' => (int)$prevReactions
        ];
    }
    
    protected function getMonthlyActivity()
    {
        $db = $this->db();
        
        $monthly = $db->fetchPairs("
            SELECT 
                MONTH(FROM_UNIXTIME(post_date)) as month,
                COUNT(*) as count
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            GROUP BY MONTH(FROM_UNIXTIME(post_date))
            ORDER BY month
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Fill in missing months with 0
        $fullYear = [];
        for ($i = 1; $i <= 12; $i++)
        {
            $fullYear[$i] = isset($monthly[$i]) ? (int)$monthly[$i] : 0;
        }
        
        // Find peak month
        $peakMonth = array_keys($fullYear, max($fullYear))[0];
        $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
        $monthNamesShort = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        $maxCount = max($fullYear);
        
        // Combine data into single array for template
        $chartData = [];
        foreach ($fullYear as $month => $count)
        {
            $chartData[] = [
                'month' => $month,
                'name' => $monthNamesShort[$month],
                'count' => $count,
                'height' => $maxCount > 0 ? round(($count / $maxCount) * 100, 2) : 0,
                'isPeak' => ($count == $maxCount && $maxCount > 0)  // Highlight highest bar
            ];
        }
        
        return [
            'data' => $fullYear,
            'chartData' => $chartData,  // For template loop
            'peakMonth' => $monthNames[$peakMonth],
            'peakMonthShort' => $monthNamesShort[$peakMonth],
            'peakCount' => max($fullYear),
            'maxCount' => $maxCount
        ];
    }
    
    protected function getWeekdayActivity()
    {
        $db = $this->db();
        
        $weekday = $db->fetchPairs("
            SELECT 
                DAYOFWEEK(FROM_UNIXTIME(post_date)) as day,
                COUNT(*) as count
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            GROUP BY DAYOFWEEK(FROM_UNIXTIME(post_date))
            ORDER BY day
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Reorder: MySQL DAYOFWEEK: 1=Sunday, 2=Monday... we want Sun-Sat
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $data = [];
        foreach ($days as $index => $day)
        {
            $mysqlDay = $index + 1; // MySQL days are 1-indexed
            $data[$day] = isset($weekday[$mysqlDay]) ? (int)$weekday[$mysqlDay] : 0;
        }
        
        // Calculate weekday vs weekend
        $weekdayTotal = $data['Mon'] + $data['Tue'] + $data['Wed'] + $data['Thu'] + $data['Fri'];
        $weekendTotal = $data['Sat'] + $data['Sun'];
        
        // Find most active day
        $mostActiveDay = array_keys($data, max($data))[0];
        
        $maxCount = max($data);
        
        // Combine data into single array for template
        $chartData = [];
        foreach ($data as $day => $count)
        {
            $chartData[] = [
                'day' => $day,
                'count' => $count,
                'height' => $maxCount > 0 ? round(($count / $maxCount) * 100, 2) : 0,
                'isPeak' => ($count == $maxCount && $maxCount > 0)  // Highlight highest bar
            ];
        }
        
        return [
            'data' => $data,
            'chartData' => $chartData,  // For template loop
            'weekdayTotal' => $weekdayTotal,
            'weekendTotal' => $weekendTotal,
            'mostActiveDay' => $mostActiveDay,
            'maxCount' => $maxCount
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
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            GROUP BY HOUR(FROM_UNIXTIME(post_date))
            ORDER BY hour
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Fill in missing hours
        $fullDay = [];
        for ($i = 0; $i < 24; $i++)
        {
            $fullDay[$i] = isset($hourly[$i]) ? (int)$hourly[$i] : 0;
        }
        
        // Find peak hour
        $peakHour = array_keys($fullDay, max($fullDay))[0];
        
        // Determine time period and specific badge
        $timePeriod = 'Night Owl'; // Default
        $peakTimeRange = '';
        
        if ($peakHour >= 0 && $peakHour < 6)
        {
            $timePeriod = 'Night Owl';
            $peakTimeRange = '12am - 6am';
        }
        elseif ($peakHour >= 6 && $peakHour < 12)
        {
            $timePeriod = 'Early Bird';
            $peakTimeRange = '6am - 12pm';
        }
        elseif ($peakHour >= 12 && $peakHour < 18)
        {
            $timePeriod = 'Afternoon Poster';
            $peakTimeRange = '12pm - 6pm';
        }
        elseif ($peakHour >= 18 && $peakHour < 24)
        {
            $timePeriod = 'Evening Warrior';
            $peakTimeRange = '6pm - 12am';
        }
        
        $maxCount = max($fullDay);
        
        // Combine data into single array for template
        $chartData = [];
        foreach ($fullDay as $hour => $count)
        {
            // Format hour for display
            $displayHour = $hour == 0 ? '12am' : ($hour < 12 ? $hour . 'am' : ($hour == 12 ? '12pm' : ($hour - 12) . 'pm'));
            
            $chartData[] = [
                'hour' => $hour,
                'displayHour' => $displayHour,
                'count' => $count,
                'height' => $maxCount > 0 ? round(($count / $maxCount) * 100, 2) : 0,
                'isPeak' => ($count == $maxCount && $maxCount > 0)  // Highlight highest bar
            ];
        }
        
        return [
            'data' => $fullDay,
            'chartData' => $chartData,  // For template loop
            'peakHour' => $peakHour,
            'timePeriod' => $timePeriod,
            'peakTimeRange' => $peakTimeRange,
            'maxCount' => $maxCount
        ];
    }
    
    protected function getPostingStyle()
    {
        $totals = $this->getTotals();
        $threads = $totals['threads'];
        $posts = $totals['posts'];
        $replies = $posts - $threads;
        
        $threadPercentage = $posts > 0 ? round(($threads / $posts) * 100) : 0;
        
        $style = 'Community Contributor'; // Default
        if ($threadPercentage > 50)
        {
            $style = 'Conversation Starter';
        }
        elseif ($threadPercentage > 30)
        {
            $style = 'Balanced Poster';
        }
        
        return [
            'threads' => $threads,
            'replies' => $replies,
            'threadPercentage' => $threadPercentage,
            'replyPercentage' => 100 - $threadPercentage,
            'style' => $style
        ];
    }
    
    protected function getTopForums()
    {
        $db = $this->db();
        
        $forums = $db->fetchAll("
            SELECT 
                n.node_id,
                n.title,
                COUNT(p.post_id) as post_count
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            INNER JOIN xf_node n ON t.node_id = n.node_id
            WHERE p.user_id = ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            GROUP BY n.node_id
            ORDER BY post_count DESC
            LIMIT 5
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Add rank numbers
        $rank = 1;
        foreach ($forums as &$forum)
        {
            $forum['rank'] = $rank++;
        }
        
        return $forums;
    }
    
    protected function getTopThreads()
    {
        $db = $this->db();
        
        $threads = $db->fetchAll("
            SELECT 
                t.thread_id,
                t.title,
                COUNT(p.post_id) as post_count
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            WHERE p.user_id = ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            GROUP BY t.thread_id
            ORDER BY post_count DESC
            LIMIT 5
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Add rank numbers
        $rank = 1;
        foreach ($threads as &$thread)
        {
            $thread['rank'] = $rank++;
        }
        
        return $threads;
    }
    
    protected function getTopInteractions()
    {
        $db = $this->db();
        
        // Users you posted in same threads with most
        $teammates = $db->fetchAll("
            SELECT 
                u.user_id,
                u.username,
                u.avatar_date,
                u.gravatar,
                COUNT(DISTINCT t.thread_id) as shared_threads
            FROM xf_post p1
            INNER JOIN xf_thread t ON p1.thread_id = t.thread_id
            INNER JOIN xf_post p2 ON t.thread_id = p2.thread_id
            INNER JOIN xf_user u ON p2.user_id = u.user_id
            WHERE p1.user_id = ?
            AND p2.user_id != ?
            AND p1.post_date >= ?
            AND p1.post_date <= ?
            GROUP BY u.user_id
            ORDER BY shared_threads DESC
            LIMIT 5
        ", [$this->user->user_id, $this->user->user_id, $this->startDate, $this->endDate]);
        
        // Convert to User entities with extra data
        $rank = 1;
        $teammateEntities = [];
        foreach ($teammates as $teammate)
        {
            $userEntity = $this->em()->find('XF:User', $teammate['user_id']);
            if ($userEntity)
            {
                $teammateEntities[] = [
                    'user' => $userEntity,
                    'shared_threads' => $teammate['shared_threads'],
                    'rank' => $rank++
                ];
            }
        }
        
        // Users who reacted to you most
        $biggestFans = $db->fetchAll("
            SELECT 
                u.user_id,
                u.username,
                u.avatar_date,
                u.gravatar,
                COUNT(*) as reaction_count
            FROM xf_reaction_content rc
            INNER JOIN xf_user u ON rc.reaction_user_id = u.user_id
            WHERE rc.content_user_id = ?
            AND rc.reaction_date >= ?
            AND rc.reaction_date <= ?
            GROUP BY u.user_id
            ORDER BY reaction_count DESC
            LIMIT 5
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Convert to User entities with extra data
        $rank = 1;
        $fanEntities = [];
        foreach ($biggestFans as $fan)
        {
            $userEntity = $this->em()->find('XF:User', $fan['user_id']);
            if ($userEntity)
            {
                $fanEntities[] = [
                    'user' => $userEntity,
                    'reaction_count' => $fan['reaction_count'],
                    'rank' => $rank++
                ];
            }
        }
        
        // Users you reacted to most
        $youReactedTo = $db->fetchAll("
            SELECT 
                u.user_id,
                u.username,
                u.avatar_date,
                u.gravatar,
                COUNT(*) as reaction_count
            FROM xf_reaction_content rc
            INNER JOIN xf_user u ON rc.content_user_id = u.user_id
            WHERE rc.reaction_user_id = ?
            AND rc.reaction_date >= ?
            AND rc.reaction_date <= ?
            GROUP BY u.user_id
            ORDER BY reaction_count DESC
            LIMIT 5
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Convert to User entities with extra data
        $rank = 1;
        $reactorEntities = [];
        foreach ($youReactedTo as $reactor)
        {
            $userEntity = $this->em()->find('XF:User', $reactor['user_id']);
            if ($userEntity)
            {
                $reactorEntities[] = [
                    'user' => $userEntity,
                    'reaction_count' => $reactor['reaction_count'],
                    'rank' => $rank++
                ];
            }
        }
        
        return [
            'teammates' => $teammateEntities,
            'biggestFans' => $fanEntities,
            'youReactedTo' => $reactorEntities
        ];
    }
    
    protected function getReactionStats()
    {
        $db = $this->db();
        
        // Get total reactions received
        $totalReceived = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE content_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Get total reactions given
        $totalGiven = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_reaction_content
            WHERE reaction_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Get most common reaction type (simplified - just count, no names for now)
        $topReaction = $db->fetchRow("
            SELECT 
                reaction_id,
                COUNT(*) as count
            FROM xf_reaction_content
            WHERE content_user_id = ?
            AND reaction_date >= ?
            AND reaction_date <= ?
            GROUP BY reaction_id
            ORDER BY count DESC
            LIMIT 1
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Simple vibe based on reaction count
        $dominantVibe = 'Active';
        if ($totalReceived > 100) {
            $dominantVibe = 'Popular';
        } elseif ($totalReceived > 50) {
            $dominantVibe = 'Well-Liked';
        } elseif ($totalReceived > 20) {
            $dominantVibe = 'Appreciated';
        }
        
        return [
            'totalReceived' => (int)$totalReceived,
            'totalGiven' => (int)$totalGiven,
            'dominantVibe' => $dominantVibe,
            'topReactionCount' => $topReaction ? (int)$topReaction['count'] : 0,
            'breakdown' => []  // Empty for now - can add later if needed
        ];
    }
    
    protected function getBookends()
    {
        $db = $this->db();
        
        // First post with thread title
        $firstPost = $db->fetchRow("
            SELECT p.post_id, p.thread_id, p.post_date, p.message, t.title as thread_title
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            WHERE p.user_id = ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            ORDER BY p.post_date ASC
            LIMIT 1
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Last post with thread title
        $lastPost = $db->fetchRow("
            SELECT p.post_id, p.thread_id, p.post_date, p.message, t.title as thread_title
            FROM xf_post p
            INNER JOIN xf_thread t ON p.thread_id = t.thread_id
            WHERE p.user_id = ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            ORDER BY p.post_date DESC
            LIMIT 1
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        return [
            'first' => $firstPost,
            'last' => $lastPost
        ];
    }
    
    protected function getLongestPost()
    {
        $db = $this->db();
        
        $longest = $db->fetchRow("
            SELECT 
                post_id, 
                thread_id, 
                LENGTH(message) - LENGTH(REPLACE(message, ' ', '')) + 1 as word_count,
                message
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            ORDER BY word_count DESC
            LIMIT 1
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        return $longest;
    }
    
    protected function getCommunityRank()
    {
        $db = $this->db();
        
        // Total active users this year
        $totalActiveUsers = $db->fetchOne("
            SELECT COUNT(DISTINCT user_id)
            FROM xf_post
            WHERE post_date >= ?
            AND post_date <= ?
        ", [$this->startDate, $this->endDate]);
        
        // This user's post count
        $userPostCount = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Count users with MORE posts than this user (corrected query)
        $usersWithMorePosts = $db->fetchOne("
            SELECT COUNT(*)
            FROM (
                SELECT user_id, COUNT(*) as post_count
                FROM xf_post
                WHERE post_date >= ?
                AND post_date <= ?
                GROUP BY user_id
                HAVING post_count > ?
            ) as user_counts
        ", [$this->startDate, $this->endDate, $userPostCount]);
        
        $rank = (int)$usersWithMorePosts + 1;
        $percentile = $totalActiveUsers > 0 ? round((1 - ($rank / $totalActiveUsers)) * 100, 1) : 0;
        
        return [
            'rank' => $rank,
            'totalActive' => (int)$totalActiveUsers,
            'percentile' => $percentile
        ];
    }
    
    protected function getWeekendVsWeekday()
    {
        $db = $this->db();
        
        // Get all posts with day of week
        $weekday = $db->fetchPairs("
            SELECT 
                DAYOFWEEK(FROM_UNIXTIME(post_date)) as day,
                COUNT(*) as count
            FROM xf_post
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            GROUP BY DAYOFWEEK(FROM_UNIXTIME(post_date))
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Calculate totals (MySQL: 1=Sun, 7=Sat)
        $weekendTotal = 0;
        $weekdayTotal = 0;
        
        foreach ($weekday as $day => $count)
        {
            if ($day == 1 || $day == 7)  // Sunday or Saturday
            {
                $weekendTotal += $count;
            }
            else
            {
                $weekdayTotal += $count;
            }
        }
        
        $totalPosts = $weekendTotal + $weekdayTotal;
        $weekendPercentage = $totalPosts > 0 ? round(($weekendTotal / $totalPosts) * 100) : 0;
        $weekdayPercentage = 100 - $weekendPercentage;
        
        // Determine badge
        $badge = 'Balanced Poster';
        $badgeEmoji = '⚖️';
        
        if ($weekendPercentage > 60)
        {
            $badge = 'Weekend Warrior';
            $badgeEmoji = '🏆';
        }
        elseif ($weekdayPercentage > 60)
        {
            $badge = 'Weekday Warrior';
            $badgeEmoji = '💼';
        }
        
        return [
            'weekendTotal' => $weekendTotal,
            'weekdayTotal' => $weekdayTotal,
            'weekendPercentage' => $weekendPercentage,
            'weekdayPercentage' => $weekdayPercentage,
            'badge' => $badge,
            'badgeEmoji' => $badgeEmoji
        ];
    }
    
    protected function getThreadStarterStats()
    {
        $db = $this->db();
        
        // Total threads started
        $threadsStarted = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Threads that got replies (reply_count > 0)
        $threadsWithReplies = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE user_id = ?
            AND post_date >= ?
            AND post_date <= ?
            AND reply_count > 0
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        // Success rate
        $successRate = $threadsStarted > 0 
            ? round(($threadsWithReplies / $threadsStarted) * 100) 
            : 0;
        
        // Average time to first reply
        $avgTimeToFirstReply = null;
        $avgTimeToFirstReplyFormatted = null;
        
        if ($threadsWithReplies > 0)
        {
            // Get threads with their first reply time
            $firstReplyTimes = $db->fetchAll("
                SELECT 
                    t.thread_id,
                    t.post_date as thread_date,
                    MIN(p.post_date) as first_reply_date
                FROM xf_thread t
                INNER JOIN xf_post p ON t.thread_id = p.thread_id
                WHERE t.user_id = ?
                AND t.post_date >= ?
                AND t.post_date <= ?
                AND p.user_id != ?
                AND p.post_date > t.post_date
                GROUP BY t.thread_id
            ", [$this->user->user_id, $this->startDate, $this->endDate, $this->user->user_id]);
            
            if (!empty($firstReplyTimes))
            {
                $totalWaitTime = 0;
                foreach ($firstReplyTimes as $times)
                {
                    $totalWaitTime += ($times['first_reply_date'] - $times['thread_date']);
                }
                
                $avgTimeToFirstReply = $totalWaitTime / count($firstReplyTimes);
                
                // Format time nicely (hours and minutes)
                $hours = floor($avgTimeToFirstReply / 3600);
                $minutes = floor(($avgTimeToFirstReply % 3600) / 60);
                
                if ($hours > 24)
                {
                    $days = floor($hours / 24);
                    $remainingHours = $hours % 24;
                    $avgTimeToFirstReplyFormatted = $days . 'd ' . $remainingHours . 'h';
                }
                elseif ($hours > 0)
                {
                    $avgTimeToFirstReplyFormatted = $hours . 'h ' . $minutes . 'm';
                }
                else
                {
                    $avgTimeToFirstReplyFormatted = $minutes . 'm';
                }
            }
        }
        
        return [
            'threadsStarted' => (int)$threadsStarted,
            'threadsWithReplies' => (int)$threadsWithReplies,
            'successRate' => $successRate,
            'avgTimeToFirstReply' => $avgTimeToFirstReply,
            'avgTimeToFirstReplyFormatted' => $avgTimeToFirstReplyFormatted
        ];
    }
    
    protected function getTopPost()
    {
        $db = $this->db();
        
        // Find post with most reactions
        $topPost = $db->fetchRow("
            SELECT 
                p.post_id,
                p.thread_id,
                p.post_date,
                p.message,
                COUNT(rc.reaction_content_id) as reaction_count
            FROM xf_post p
            LEFT JOIN xf_reaction_content rc ON p.post_id = rc.content_id 
                AND rc.content_type = 'post'
            WHERE p.user_id = ?
            AND p.post_date >= ?
            AND p.post_date <= ?
            GROUP BY p.post_id
            ORDER BY reaction_count DESC
            LIMIT 1
        ", [$this->user->user_id, $this->startDate, $this->endDate]);
        
        if ($topPost)
        {
            // Get thread title
            $thread = $this->em()->find('XF:Thread', $topPost['thread_id']);
            
            // Extract a snippet from the post (first 200 chars)
            $message = strip_tags($topPost['message']);
            $snippet = strlen($message) > 200 
                ? substr($message, 0, 200) . '...' 
                : $message;
            
            return [
                'post_id' => $topPost['post_id'],
                'thread_id' => $topPost['thread_id'],
                'thread_title' => $thread ? $thread->title : '',
                'post_date' => $topPost['post_date'],
                'reaction_count' => (int)$topPost['reaction_count'],
                'snippet' => $snippet
            ];
        }
        
        return null;
    }
}
