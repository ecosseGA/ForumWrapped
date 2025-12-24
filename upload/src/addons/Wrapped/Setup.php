<?php

namespace IdleChatter\Wrapped;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUninstallTrait;
    use StepRunnerUpgradeTrait;

    // No database tables needed - we use existing XenForo tables
    
    public function installStep1()
    {
        // Nothing to install - addon uses existing XF tables
    }

    public function uninstallStep1()
    {
        // Nothing to uninstall - no custom tables
    }
}
