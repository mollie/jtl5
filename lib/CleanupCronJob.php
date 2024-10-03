<?php

namespace Plugin\ws5_mollie\lib;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use Plugin\ws5_mollie\lib\Model\QueueModel;

class CleanupCronJob  extends Job
{
    public function start(QueueEntry $queueEntry): JobInterface
    {
        parent::start($queueEntry);

        try {
            $this->logger->debug('Mollie Queue Cleanup');
            ifndef('MOLLIE_DISABLE_USER_CLEANUP', false);
            if (!MOLLIE_DISABLE_USER_CLEANUP) {
                QueueModel::cleanUp();
            }

            PluginHelper::cleanupPaymentLogs();

        } catch (\Exception $e) {
            $this->logger->debug('Mollie Queue Exception: ' . $e->getMessage());
        }

        $this->setFinished(true);
        return $this;
    }
}
