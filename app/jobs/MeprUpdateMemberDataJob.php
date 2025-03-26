<?php

/**
 * Job to update member data in batches.
 *
 * @property integer $limit The batch limit.
 */
class MeprUpdateMemberDataJob extends MeprBaseJob
{
    /**
     * Perform this job.
     */
    public function perform()
    {
        MeprMigrator::before_start();

        $fetch_batch = function ($limit) {
            return MeprUser::get_update_all_member_data_user_ids(true, $limit);
        };

        $processor = new MeprBatchMigrator($fetch_batch, $this->limit ?? 10, 0);

        while ($processor->next_batch()) {
            $user_ids = $processor->get_items();

            foreach ($user_ids as $user_id) {
                MeprUser::update_member_data_static($user_id);
            }
        }

        MeprMigrator::finish();

        if ($processor->has_items()) {
            $job        = new MeprUpdateMemberDataJob();
            $job->limit = $processor->get_limit();
            $job->enqueue();
        }
    }
}
