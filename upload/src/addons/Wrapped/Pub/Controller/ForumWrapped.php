<?php

namespace IdleChatter\Wrapped\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class ForumWrapped extends AbstractController
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
        
        /** @var \IdleChatter\Wrapped\Service\ForumStats $statsService */
        $statsService = $this->service('IdleChatter\Wrapped:ForumStats', $year);
        
        // Calculate all stats
        $stats = $statsService->getStats();
        
        // If no activity, show message
        if ($stats['overview']['totalPosts'] == 0)
        {
            return $this->message(\XF::phrase('wrapped_no_forum_activity_year', ['year' => $year]));
        }
        
        $viewParams = [
            'year' => $year,
            'stats' => $stats,
            'currentYear' => $currentYear,
            'prevYear' => $year - 1,
            'nextYear' => $year + 1
        ];
        
        return $this->view('IdleChatter\Wrapped:ForumWrapped\View', 'forum_wrapped_view', $viewParams);
    }
}
