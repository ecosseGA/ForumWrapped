<?php

namespace IdleChatter\Wrapped\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Wrapped extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        // Get the year from URL or default to current year
        $year = $this->filter('year', 'uint');
        if (!$year)
        {
            $year = (int)date('Y');
        }
        
        // Validate year (must be >= 2020 and <= current year)
        $currentYear = (int)date('Y');
        if ($year < 2020 || $year > $currentYear)
        {
            return $this->error(\XF::phrase('wrapped_invalid_year'));
        }
        
        // Get current visitor
        $visitor = \XF::visitor();
        
        // Must be logged in
        if (!$visitor->user_id)
        {
            return $this->noPermission();
        }
        
        // Get user ID from URL or use visitor
        $userId = $this->filter('user_id', 'uint');
        if (!$userId)
        {
            $userId = $visitor->user_id;
        }
        
        // Users can only view their own wrapped
        if ($userId != $visitor->user_id)
        {
            return $this->noPermission(\XF::phrase('wrapped_can_only_view_own'));
        }
        
        // Get the user
        $user = $this->assertRecordExists('XF:User', $userId);
        
        /** @var \IdleChatter\Wrapped\Service\Stats $statsService */
        $statsService = $this->service('IdleChatter\Wrapped:Stats', $user, $year);
        
        // Calculate all stats
        $stats = $statsService->getStats();
        
        // If no activity, show message
        if ($stats['totals']['posts'] == 0)
        {
            return $this->message(\XF::phrase('wrapped_no_activity_year', ['year' => $year]));
        }
        
        $viewParams = [
            'user' => $user,
            'year' => $year,
            'stats' => $stats,
            'currentYear' => $currentYear,
            'prevYear' => $year - 1,
            'nextYear' => $year + 1,
            'postChange' => $stats['totals']['posts'] - $stats['yearOverYear']['prevPosts'],
            'reactionChange' => $stats['totals']['reactionsReceived'] - $stats['yearOverYear']['prevReactions']
        ];
        
        return $this->view('IdleChatter\Wrapped:View', 'wrapped_view', $viewParams);
    }
}
