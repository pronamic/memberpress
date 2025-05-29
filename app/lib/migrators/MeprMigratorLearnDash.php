<?php

use memberpress\courses\controllers\admin\CourseCategories;
use memberpress\courses\controllers\admin\CourseTags;
use memberpress\courses\helpers\Courses;
use memberpress\courses\helpers\Lessons;
use memberpress\courses\lib\Utils;
use memberpress\courses\models\Course;
use memberpress\courses\models\Lesson;
use memberpress\quizzes\helpers\App;
use memberpress\quizzes\models\Answer;
use memberpress\quizzes\models\Attempt;
use memberpress\quizzes\models\Question;
use memberpress\quizzes\models\Quiz;
use memberpress\courses\models\Section;
use memberpress\courses\models\UserProgress;

class MeprMigratorLearnDash extends MeprMigrator
{
    /**
     * The unique key for this migrator.
     *
     * @var string
     */
    public const KEY = 'learndash';

    /**
     * Mapping of LD question type -> MP question type.
     *
     * @var array
     */
    protected const QUESTION_TYPE_MAP = [
        'single'             => 'multiple-choice',
        'multiple'           => 'multiple-answer',
        'free_answer'        => 'short-answer',
        'sort_answer'        => 'sort-values',
        'matrix_sort_answer' => 'match-matrix',
        'cloze_answer'       => 'fill-blank',
        'assessment_answer'  => 'likert-scale',
        'essay'              => 'essay',
    ];

    /**
     * Do the migration based on the given data.
     *
     * @param array $data The data array for the current step.
     */
    public function migrate(array $data)
    {
        if (!empty($data['step'])) {
            switch ($data['step']) {
                case 'start':
                    $this->check_prerequisites($data);
                    break;
                case 'taxonomies':
                    $this->migrate_taxonomies($data);
                    break;
                case 'courses':
                    $this->migrate_courses($data);
                    break;
                case 'progress':
                    $this->migrate_progress($data);
                    break;
                case 'attempts':
                    $this->migrate_attempts($data);
                    break;
            }
        }

        wp_send_json_error(__('Bad request', 'memberpress'));
    }

    /**
     * Check that the migrator prerequisites are met.
     *
     * Makes sure that the tables exist for our later queries.
     *
     * @param array $data The data for the current request.
     *
     * @return void
     */
    protected function check_prerequisites(array $data)
    {
        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $tables = [
            "{$wpdb->prefix}learndash_pro_quiz_master",
            "{$wpdb->prefix}learndash_pro_quiz_question",
        ];

        if (!empty($data['options']['userProgress'])) {
            $tables = array_merge($tables, [
                "{$wpdb->prefix}learndash_pro_quiz_statistic",
                "{$wpdb->prefix}learndash_user_activity",
                "{$wpdb->prefix}learndash_user_activity_meta",
            ]);
        }

        foreach ($tables as $table) {
            if (!$mepr_db->table_exists($table)) {
                wp_send_json_error(
                    sprintf(
                        // Translators: %s: the table name.
                        __('Migration not started: table `%s` not found, try updating LearnDash to the latest version first.', 'memberpress'),
                        $table
                    )
                );
            }
        }

        if ($this->is_courses_addon_active()) {
            if (version_compare(memberpress\courses\VERSION, '1.3.6', '<=')) {
                wp_send_json_error(
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    __('Please update the MemberPress Courses plugin to the latest version and try again.', 'memberpress')
                );
            } else {
                if (
                    empty($data['retry-quizzes']) &&
                    !$this->is_course_quizzes_addon_active() &&
                    file_exists(WP_PLUGIN_DIR . '/memberpress-course-quizzes/main.php')
                ) {
                    activate_plugin('memberpress-course-quizzes/main.php');

                    if (
                        $this->is_course_quizzes_addon_active() &&
                        function_exists('memberpress\\quizzes\\init') &&
                        defined('LEARNDASH_VERSION') &&
                        class_exists('LearnDash_Settings_Section') &&
                        method_exists('LearnDash_Settings_Section', 'get_section_setting')
                    ) {
                        $options = get_option('mpcs-options');
                        $options = is_array($options) ? $options : [];
                        $update  = false;

                        $quizzes_slug = LearnDash_Settings_Section::get_section_setting(
                            'LearnDash_Settings_Section_Permalinks',
                            'quizzes'
                        );

                        memberpress\quizzes\init();

                        if ($quizzes_slug == App::get_quizzes_permalink_base()) {
                            $options['quizzes-slug'] = 'mp-quizzes';
                            $update                  = true;
                        }

                        if ($update) {
                            update_option('mpcs-options', $options);
                            delete_option('mepr_courses_flushed_rewrite_rules');
                        }

                        $this->send_success_response($data, [
                            'step'          => 'start',
                            'retry-quizzes' => 'true',
                        ]);
                    }
                }
            }
        } else {
            if (file_exists(WP_PLUGIN_DIR . '/memberpress-courses/main.php')) {
                if (empty($data['retry'])) {
                    activate_plugin('memberpress-courses/main.php');

                    if (
                        $this->is_courses_addon_active() &&
                        version_compare(memberpress\courses\VERSION, '1.3.6', '>') &&
                        defined('LEARNDASH_VERSION') &&
                        class_exists('LearnDash_Settings_Section') &&
                        method_exists('LearnDash_Settings_Section', 'get_section_setting')
                    ) {
                        // Handles any conflicts with post type slugs between LearnDash and MemberPress Courses.
                        // If any slugs are the same, we'll set a different slug to avoid breaking existing courses.
                        $courses_slug = LearnDash_Settings_Section::get_section_setting(
                            'LearnDash_Settings_Section_Permalinks',
                            'courses'
                        );

                        $lessons_slug = LearnDash_Settings_Section::get_section_setting(
                            'LearnDash_Settings_Section_Permalinks',
                            'lessons'
                        );

                        $options = get_option('mpcs-options');
                        $options = is_array($options) ? $options : [];
                        $update  = false;

                        if ($courses_slug == Courses::get_permalink_base()) {
                            $options['courses-slug'] = 'learn';
                            $update                  = true;
                        }

                        if ($lessons_slug == Lessons::get_permalink_base()) {
                            $options['lessons-slug'] = 'mp-lessons';
                            $update                  = true;
                        }

                        if ($update) {
                            update_option('mpcs-options', $options);
                            delete_option('mepr_courses_flushed_rewrite_rules');
                        }
                    }

                    $this->send_success_response($data, [
                        'step'  => 'start',
                        'retry' => 'true',
                    ]);
                }

                wp_send_json_error(
                    __('Please activate the MemberPress Courses plugin and try again.', 'memberpress')
                );
            } else {
                wp_send_json_error(
                    __('Please install and activate the MemberPress Courses plugin and try again.', 'memberpress')
                );
            }
        }

        $this->send_success_response($data, ['step' => 'taxonomies']);
    }

    /**
     * Get the enabled LD taxonomies.
     *
     * @return array
     */
    protected function get_enabled_taxonomies(): array
    {
        $settings   = get_option('learndash_settings_courses_taxonomies');
        $taxonomies = [];

        if (is_array($settings)) {
            if (!empty($settings['ld_course_category']) && $settings['ld_course_category'] == 'yes') {
                $taxonomies['ld_course_category'] = CourseCategories::$tax;
            }

            if (!empty($settings['wp_post_category']) && $settings['wp_post_category'] == 'yes') {
                $taxonomies['category'] = CourseCategories::$tax;
            }

            if (!empty($settings['ld_course_tag']) && $settings['ld_course_tag'] == 'yes') {
                $taxonomies['ld_course_tag'] = CourseTags::$tax;
            }

            if (!empty($settings['wp_post_tag']) && $settings['wp_post_tag'] == 'yes') {
                $taxonomies['post_tag'] = CourseTags::$tax;
            }
        }

        return $taxonomies;
    }

    /**
     * Migrate course taxonomies.
     *
     * @param array $data The data for the current request.
     */
    protected function migrate_taxonomies(array $data)
    {
        $taxonomies = $this->get_enabled_taxonomies();

        if (count($taxonomies)) {
            self::before_start();

            list($limit, $offset) = $this->get_request_limit_offset($data, 10);

            $fetch_batch = function ($limit, $offset) use ($taxonomies) {
                $terms = get_terms([
                    'taxonomy'   => array_keys($taxonomies),
                    'hide_empty' => false,
                    'orderby'    => 'term_id',
                    'number'     => $limit,
                    'offset'     => $offset,
                ]);

                return is_array($terms) ? $terms : [];
            };

            $processor = new MeprBatchMigrator($fetch_batch, $limit, $offset);

            while ($processor->next_batch()) {
                /**
                 * Terms to migrate.
                 *
                 * @var WP_Term[] $terms
                 */
                $terms = $processor->get_items();

                foreach ($terms as $term) {
                    if (!empty($taxonomies[$term->taxonomy])) {
                        wp_insert_term($term->name, $taxonomies[$term->taxonomy], [
                            'description' => $term->description,
                            'slug'        => $term->slug,
                        ]);
                    }
                }
            }

            self::finish();

            if ($processor->has_items()) {
                $this->send_success_response($data, [
                    'step'   => 'taxonomies',
                    'offset' => $processor->get_offset(),
                    'limit'  => $processor->get_limit(),
                ]);
            }
        }

        $this->send_success_response($data, [
            'step' => 'courses',
            'logs' => $this->logs,
        ]);
    }

    /**
     * Migrate courses.
     *
     * @param array $data The data for the current request.
     *
     * @return void
     */
    protected function migrate_courses(array $data)
    {
        self::before_start();

        list($limit, $offset) = $this->get_request_limit_offset($data, 1);

        $fetch_batch = function ($limit, $offset) {
            global $wpdb;

            $query = "SELECT * FROM {$wpdb->posts}
                WHERE post_type = 'sfwd-courses'
                AND post_status = 'publish'
                ORDER BY ID ASC
                LIMIT %d OFFSET %d";

            $courses = $wpdb->get_results($wpdb->prepare($query, $limit, $offset));

            return is_array($courses) ? $courses : [];
        };

        $processor = new MeprBatchMigrator($fetch_batch, $limit, $offset);

        while ($processor->next_batch()) {
            $courses = $processor->get_items();

            foreach ($courses as $ld_course) {
                $course_data = [
                    'post_title'    => $ld_course->post_title,
                    'post_content'  => $ld_course->post_content,
                    'post_excerpt'  => $ld_course->post_excerpt,
                    'post_name'     => $ld_course->post_name,
                    'post_date'     => $ld_course->post_date,
                    'post_date_gmt' => $ld_course->post_date_gmt,
                    'post_status'   => 'publish',
                ];

                $settings = get_post_meta($ld_course->ID, '_sfwd-courses', true);

                if (is_array($settings)) {
                    if (
                        !empty($settings['sfwd-courses_course_materials_enabled']) &&
                        $settings['sfwd-courses_course_materials_enabled'] == 'on' &&
                        !empty($settings['sfwd-courses_course_materials'])
                    ) {
                        $resources = Courses::get_default_resources();

                        $resources['sections']['custom'] = [
                            'id'    => 'custom',
                            'label' => '',
                            'items' => ['custom'],
                        ];

                        $resources['items']['custom'] = [
                            'id'      => 'custom',
                            'content' => $settings['sfwd-courses_course_materials'],
                        ];

                        $course_data['resources'] = wp_json_encode($resources);
                    } else {
                        $course_data['resources'] = '';
                    }

                    $disable_progression = !empty($settings['sfwd-courses_course_disable_lesson_progression']) &&
                                 $settings['sfwd-courses_course_disable_lesson_progression'] == 'on';

                    $course_data['require_previous'] = $disable_progression ? 'disabled' : 'enabled';
                }

                try {
                    /**
                     * Course to migrate.
                     *
                     * @var Course $course
                     */
                    $course = $this->migrate_model(
                        Course::class,
                        $this->get_existing_course_id((int) $ld_course->ID),
                        $course_data
                    );

                    update_post_meta($course->ID, '_mepr_learndash_course_id', $ld_course->ID);

                    $this->migrate_course_taxonomies($course->ID, $ld_course->ID);

                    $this->migrate_lessons($course, $ld_course);
                } catch (Exception $e) {
                    $this->logs[] = $this->model_migration_failed_log(
                        Course::class,
                        !empty($course_data['post_title']) ? $course_data['post_title'] : __('Untitled', 'memberpress'),
                        $ld_course->ID,
                        $e->getMessage()
                    );
                }
            }
        }

        self::finish();

        if ($processor->has_items()) {
            $this->send_success_response($data, [
                'step'   => 'courses',
                'offset' => $processor->get_offset(),
                'limit'  => $processor->get_limit(),
                'logs'   => $this->logs,
            ]);
        }

        if (empty($data['options']['userProgress'])) {
            update_option('mepr_migrator_learndash_completed', true);

            $this->send_success_response($data, [
                'status' => 'complete',
                'logs'   => $this->logs,
            ]);
        } else {
            $this->send_success_response($data, [
                'step' => 'progress',
                'logs' => $this->logs,
            ]);
        }
    }

    /**
     * Get the ID of the MP Course that has been previously migrated for the given LD Course ID.
     *
     * @param  integer $ld_course_id The LearnDash course ID.
     * @return integer
     */
    protected function get_existing_course_id(int $ld_course_id): int
    {
        global $wpdb;

        $query = "SELECT p.ID
              FROM $wpdb->posts p
              INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_mepr_learndash_course_id'
              WHERE p.post_type = %s
              AND p.post_status = 'publish'
              AND pm.meta_value = %d";

        $query = $wpdb->prepare($query, Course::$cpt, $ld_course_id);

        return (int) $wpdb->get_var($query);
    }

    /**
     * Migrate the taxonomies for the given course.
     *
     * @param integer $course_id    The MemberPress course ID.
     * @param integer $ld_course_id The LearnDash course ID.
     *
     * @return void
     */
    protected function migrate_course_taxonomies($course_id, $ld_course_id)
    {
        $taxonomies = $this->get_enabled_taxonomies();

        if (count($taxonomies)) {
            $categories = array_values(
                array_intersect(
                    array_keys($taxonomies),
                    ['ld_course_category', 'category']
                )
            );

            if (!empty($categories)) {
                $terms = wp_get_object_terms($ld_course_id, $categories, ['fields' => 'slugs']);

                if (is_array($terms)) {
                    wp_set_object_terms($course_id, $terms, CourseCategories::$tax);
                }
            }

            $tags = array_values(
                array_intersect(
                    array_keys($taxonomies),
                    ['ld_course_tag', 'post_tag']
                )
            );

            if (!empty($tags)) {
                $terms = wp_get_object_terms($ld_course_id, $tags, ['fields' => 'slugs']);

                if (is_array($terms)) {
                    wp_set_object_terms($course_id, $terms, CourseTags::$tax);
                }
            }
        }
    }

    /**
     * Migrate the lessons and quizzes for the given course.
     *
     * @param Course   $course    The course object.
     * @param stdClass $ld_course The LearnDash course object.
     *
     * @throws Exception If migration fails.
     */
    protected function migrate_lessons(Course $course, $ld_course)
    {
        $sections = $this->populate_course_sections(
            $ld_course->ID,
            $this->get_course_sections($ld_course->ID)
        );

        $existing_sections = $this->filter_existing_sections(
            $course->sections(),
            wp_list_pluck($sections, 'uuid')
        );

        $lessons_by_section = [];

        foreach (array_values($sections) as $section_order => $section_data) {
            $section_id = $existing_sections[$section_data['uuid']] ?? 0;

            $section                = new Section($section_id);
            $section->title         = $section_data['title'];
            $section->course_id     = $course->ID;
            $section->section_order = $section_order;
            $section->uuid          = $section_data['uuid'];

            $section_id = $section->store();

            if ($section_id instanceof WP_Error || empty($section_id)) {
                $this->logs[] = sprintf(
                    // Translators: %1$s: section title, %2$s: error message.
                    __('Failed to migrate course section "%1$s": %2$s', 'memberpress'),
                    $section_data['title'],
                    $section_id instanceof WP_Error ? $section_id->get_error_message() : 'section ID was empty'
                );

                continue;
            }

            $lessons_by_section[$section_id] = [];

            if (count($section_data['children'])) {
                foreach ($section_data['children'] as $lesson_order => $ld_lesson_id) {
                    $ld_lesson = get_post($ld_lesson_id);

                    if ($ld_lesson instanceof WP_Post) {
                        $post_types = ['sfwd-lessons', 'sfwd-topic'];

                        if ($this->is_course_quizzes_addon_active()) {
                            $post_types[] = 'sfwd-quiz';
                        }

                        if (!in_array($ld_lesson->post_type, $post_types, true)) {
                            continue;
                        }

                        $post_content = $ld_lesson->post_content;
                        $materials    = $this->get_lesson_materials($ld_lesson);

                        if (!empty($materials)) {
                            $post_content .= "\n\n";
                            $post_content .= sprintf('<h3>%s</h3>', esc_html__('Materials', 'memberpress'));
                            $post_content .= "\n\n";
                            $post_content .= $materials;
                        }

                        $lesson_data = [
                            'post_title'    => $ld_lesson->post_title,
                            'post_content'  => $post_content,
                            'post_excerpt'  => $ld_lesson->post_excerpt,
                            'post_name'     => $ld_lesson->post_name,
                            'post_date'     => $ld_lesson->post_date,
                            'post_date_gmt' => $ld_lesson->post_date_gmt,
                            'post_status'   => 'publish',
                            'section_id'    => $section_id,
                            'lesson_order'  => $lesson_order,
                        ];

                        try {
                            $model = $this->migrate_model(
                                $ld_lesson->post_type == 'sfwd-quiz' ? Quiz::class : Lesson::class,
                                $this->get_existing_lesson_id(
                                    $ld_course->ID,
                                    $ld_lesson->ID,
                                    $ld_lesson->post_type == 'sfwd-quiz' ? Quiz::$cpt : Lesson::$cpt
                                ),
                                $lesson_data
                            );

                            update_post_meta($model->ID, '_mepr_learndash_course_id', $ld_course->ID);
                            update_post_meta($model->ID, '_mepr_learndash_lesson_id', $ld_lesson->ID);

                            $lessons_by_section[$section_id][] = $model->ID;

                            if ($model instanceof Quiz) {
                                $this->migrate_quiz($model, $ld_lesson);
                            }
                        } catch (Exception $e) {
                            if (!empty($lesson_data['post_title'])) {
                                $title = $lesson_data['post_title'];
                            } else {
                                $title = __('Untitled', 'memberpress');
                            }

                            $this->logs[] = $this->model_migration_failed_log(
                                $ld_lesson->post_type == 'sfwd-quiz' ? Quiz::class : Lesson::class,
                                $title,
                                $ld_lesson->ID,
                                $e->getMessage()
                            );
                        }
                    }
                }
            }

            foreach ($lessons_by_section as $section_id => $lesson_ids) {
                $section = new Section($section_id);

                foreach ($section->lessons() as $lesson) {
                    if (!in_array($lesson->ID, $lesson_ids, true)) {
                        $lesson->remove_from_section();
                    }
                }
            }
        }
    }

    /**
     * Get the materials for an LD Lesson, Topic or Quiz (if any).
     *
     * @param  WP_Post $ld_lesson The LearnDash lesson, topic or quiz post instance.
     * @return string|null
     */
    protected function get_lesson_materials($ld_lesson)
    {
        $settings = get_post_meta($ld_lesson->ID, "_$ld_lesson->post_type", true);

        if (is_array($settings)) {
            switch ($ld_lesson->post_type) {
                case 'sfwd-lessons':
                    $key = "{$ld_lesson->post_type}_lesson";
                    break;
                case 'sfwd-topic':
                    $key = "{$ld_lesson->post_type}_topic";
                    break;
                case 'sfwd-quiz':
                    $key = "{$ld_lesson->post_type}_quiz";
                    break;
                default:
                    return null;
            }

            if (
                !empty($settings["{$key}_materials_enabled"]) &&
                $settings["{$key}_materials_enabled"] == 'on' &&
                !empty($settings["{$key}_materials"])
            ) {
                return $settings["{$key}_materials"];
            }
        }

        return null;
    }

    /**
     * Migrate a single quiz and the questions within it.
     *
     * @param Quiz    $quiz      The MP Courses quiz instance.
     * @param WP_Post $ld_lesson The LD Quiz post instance.
     *
     * @throws Exception If migration fails.
     */
    protected function migrate_quiz(Quiz $quiz, $ld_lesson)
    {
        global $wpdb;

        $quiz_pro_id = get_post_meta($ld_lesson->ID, 'quiz_pro_id', true);

        if (is_numeric($quiz_pro_id)) {
            $ld_questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}learndash_pro_quiz_question
                    WHERE quiz_id = %d
                    AND online = 1
                    ORDER BY sort",
                    $quiz_pro_id
                )
            );

            if (!empty($ld_questions)) {
                $questions          = [];
                $required           = false;
                $settings           = get_post_meta($ld_lesson->ID, '_sfwd-quiz', true);
                $migrated_questions = get_post_meta($quiz->ID, '_mepr_learndash_migrated_questions', true);
                $migrated_questions = is_array($migrated_questions) ? $migrated_questions : [];

                if (is_array($settings)) {
                    $required = !empty($settings['sfwd-quiz_forcingQuestionSolve']);
                }

                foreach ($ld_questions as $i => $ld_question) {
                    if (!array_key_exists($ld_question->answer_type, self::QUESTION_TYPE_MAP)) {
                        continue;
                    }

                    $question_data = [
                        'quiz_id'  => $quiz->ID,
                        'number'   => $i + 1,
                        'text'     => wp_strip_all_tags($ld_question->question),
                        'type'     => self::QUESTION_TYPE_MAP[$ld_question->answer_type],
                        'required' => (int) $required,
                        'points'   => (int) $ld_question->points,
                        'feedback' => $ld_question->incorrect_msg,
                    ];

                    $answer_data = $this->parse_question_answer_data($ld_question->answer_data);

                    switch ($ld_question->answer_type) {
                        case 'single':
                            $question_data['options'] = [];

                            foreach ($answer_data as $index => $option) {
                                if (isset($option['_answer']) && is_string($option['_answer'])) {
                                    $question_data['options'][] = $option['_answer'];
                                } else {
                                    $question_data['options'][] = '';
                                }

                                if (!empty($option['_correct'])) {
                                    $question_data['answer'] = $index;
                                }
                            }

                            if (empty($question_data['options'])) {
                                      $question_data['options'][] = '';
                            }

                            if (!isset($question_data['answer']) || !is_numeric($question_data['answer'])) {
                                      $question_data['answer'] = 0;
                            }
                            break;
                        case 'multiple':
                            $question_data['options'] = [];
                            $question_data['answer']  = [];

                            foreach ($answer_data as $index => $option) {
                                if (isset($option['_answer']) && is_string($option['_answer'])) {
                                    $question_data['options'][] = $option['_answer'];
                                } else {
                                    $question_data['options'][] = '';
                                }

                                if (!empty($option['_correct'])) {
                                    $question_data['answer'][] = $index;
                                }
                            }

                            if (empty($question_data['options'])) {
                                $question_data['options'][] = '';
                            }

                            if (empty($question_data['answer'])) {
                                $question_data['answer'] = [0];
                            }
                            break;
                        case 'free_answer':
                        case 'essay':
                            // No additional processing required.
                            break;
                        case 'sort_answer':
                            $question_data['options'] = [];

                            foreach ($answer_data as $option) {
                                if (isset($option['_answer']) && is_string($option['_answer'])) {
                                    $question_data['options'][] = $option['_answer'];
                                } else {
                                    $question_data['options'][] = '';
                                }
                            }
                            break;
                        case 'matrix_sort_answer':
                            $question_data['options'] = [];
                            $question_data['answer']  = [];

                            foreach ($answer_data as $option) {
                                if (isset($option['_answer']) && is_string($option['_answer'])) {
                                    $question_data['options'][] = $option['_answer'];
                                } else {
                                    $question_data['options'][] = '';
                                }

                                if (isset($option['_sortString']) && is_string($option['_sortString'])) {
                                    $question_data['answer'][] = $option['_sortString'];
                                } else {
                                    $question_data['answer'][] = '';
                                }
                            }
                            break;
                        case 'cloze_answer':
                            if (isset($answer_data[0]['_answer']) && is_string($answer_data[0]['_answer'])) {
                                $answer = $answer_data[0]['_answer'];
                            } else {
                                $answer = '';
                            }

                            $question_data['answer'] = $this->convert_fill_blank_answer($answer);
                            break;
                        case 'assessment_answer':
                            if (isset($answer_data[0]['_answer']) && is_string($answer_data[0]['_answer'])) {
                                $answer = $answer_data[0]['_answer'];
                            } else {
                                $answer = '';
                            }

                            $likert_scale_answer = $this->convert_likert_scale_answer($answer);

                            list($question_data['options'], $question_data['settings']) = $likert_scale_answer;
                            break;
                        default:
                            continue 2;
                    }

                    if (!empty($migrated_questions[$ld_question->id])) {
                        $existing_id = $migrated_questions[$ld_question->id];
                    } else {
                        $existing_id = 0;
                    }

                    try {
                        $question                             = $this->migrate_model(Question::class, $existing_id, $question_data);
                        $questions[]                          = $question;
                        $migrated_questions[$ld_question->id] = $question->id;
                    } catch (Exception $e) {
                        $this->logs[] = $this->model_migration_failed_log(
                            Question::class,
                            !empty($question_data['text']) ? $question_data['text'] : __('Untitled', 'memberpress'),
                            $ld_question->id,
                            $e->getMessage()
                        );
                    }
                }

                $content = [];

                if ($quiz->post_content !== '' && count($questions)) {
                    $content[] = ''; // Adds two newlines below, after existing content.
                }

                foreach ($questions as $question) {
                    $block     = "<!-- wp:memberpress-courses/$question->type-question {\"questionId\":$question->id} /-->";
                    $content[] = $block;
                }

                $quiz->post_content = $quiz->post_content . implode("\n\n", $content);
                $quiz->store();

                update_post_meta($quiz->ID, '_mepr_learndash_migrated_questions', $migrated_questions);
            }
        }
    }

    /**
     * Get the ID of the MP Lesson or Quiz that has been previously migrated for the given LD Lesson/Topic/Quiz ID.
     *
     * @param integer $ld_course_id The LearnDash course ID.
     * @param integer $ld_lesson_id The LearnDash lesson ID.
     * @param string  $post_type    The post type (lesson or quiz).
     *
     * @return integer The ID of the migrated lesson or quiz.
     */
    protected function get_existing_lesson_id($ld_course_id, $ld_lesson_id, $post_type): int
    {
        global $wpdb;

        $query = "SELECT p.ID
              FROM $wpdb->posts p
              INNER JOIN $wpdb->postmeta pm1 ON pm1.post_id = p.ID AND pm1.meta_key = '_mepr_learndash_course_id'
              INNER JOIN $wpdb->postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_mepr_learndash_lesson_id'
              WHERE p.post_type = %s
              AND p.post_status = 'publish'
              AND pm1.meta_value = %d
              AND pm2.meta_value = %d";

        $query = $wpdb->prepare($query, $post_type, $ld_course_id, $ld_lesson_id);

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get the array of course sections for the given LD Course ID.
     *
     * @param  integer $ld_course_id The LearnDash course ID.
     * @return array
     */
    protected function get_course_sections($ld_course_id): array
    {
        $course_sections = get_post_meta($ld_course_id, 'course_sections', true);
        $sections        = [];

        if (!empty($course_sections)) {
            $course_sections = json_decode($course_sections);

            if (is_array($course_sections)) {
                foreach ($course_sections as $course_section) {
                    if (
                        is_object($course_section) &&
                        property_exists($course_section, 'ID') && !empty($course_section->ID) &&
                        property_exists($course_section, 'order') &&
                        property_exists($course_section, 'post_title') && !empty($course_section->post_title)
                    ) {
                        $sections[(int) $course_section->order] = [
                            'title'    => $course_section->post_title,
                            'uuid'     => substr("ld-course-$ld_course_id-" . dechex($course_section->ID), 0, 40),
                            'children' => [],
                        ];
                    }
                }

                // Sort sections in the order they appear.
                ksort($sections);

                if (key($sections) !== 0) {
                    // LD can have lessons outside (before) a section, but this isn't possible in Courses. So we force the
                    // first section to start from index 0 to ensure that all lessons and quizzes are inside a section.
                    foreach ($sections as $key => $section) {
                        $sections[0] = $section;
                        unset($sections[$key]);
                        break;
                    }

                    // Re-sort sections after changing the key.
                    ksort($sections);
                }
            }
        }

        if (empty($sections)) {
            $sections[] = [
                'title'    => __('Lessons', 'memberpress'),
                'uuid'     => substr("ld-course-$ld_course_id-default", 0, 40),
                'children' => [],
            ];
        }

        return $sections;
    }

    /**
     * Populate each section with the IDs for lessons, topics and quizzes contained within it.
     *
     * @param integer $ld_course_id The LearnDash course ID.
     * @param array   $sections     The sections array.
     *
     * @return array The populated sections array.
     */
    protected function populate_course_sections($ld_course_id, $sections): array
    {
        $ld_course_steps = get_post_meta($ld_course_id, 'ld_course_steps', true);

        if (isset($ld_course_steps['steps']['h']) && is_array($ld_course_steps['steps']['h'])) {
            $steps = $ld_course_steps['steps']['h'];
        } else {
            $steps = [];
        }

        $lessons = isset($steps['sfwd-lessons']) && is_array($steps['sfwd-lessons']) ? $steps['sfwd-lessons'] : [];

        // Add the section dividers into the list of lessons.
        foreach ($sections as $order => $section) {
            $lessons = array_slice($lessons, 0, $order, true) +
            ["section-$order" => $order] +
            array_slice($lessons, $order, null, true);
        }

        // Flatten the course structure into the two-level structure supported by MemberPress.
        $key = 0;

        foreach ($lessons as $lesson_post_id => $ld_lesson) {
            if (is_string($lesson_post_id) && preg_match('/section-\d+/', $lesson_post_id)) {
                $key = $ld_lesson; // This is a section divider, move to the next section.
                continue;
            }

            $sections[$key]['children'][] = $lesson_post_id;

            // Topic that is a child of this ld_lesson.
            if (isset($ld_lesson['sfwd-topic']) && is_array($ld_lesson['sfwd-topic'])) {
                foreach ($ld_lesson['sfwd-topic'] as $topic_post_id => $topic) {
                    $sections[$key]['children'][] = $topic_post_id;

                    if ($this->is_course_quizzes_addon_active()) {
                        // Quiz that is a child of this topic.
                        if (isset($topic['sfwd-quiz']) && is_array($topic['sfwd-quiz'])) {
                            foreach ($topic['sfwd-quiz'] as $quiz_post_id => $quiz) {
                                $sections[$key]['children'][] = $quiz_post_id;
                            }
                        }
                    }
                }
            }

            if ($this->is_course_quizzes_addon_active()) {
                // Quiz that is a child of this ld_lesson.
                if (isset($ld_lesson['sfwd-quiz']) && is_array($ld_lesson['sfwd-quiz'])) {
                    foreach ($ld_lesson['sfwd-quiz'] as $quiz_post_id => $quiz) {
                        $sections[$key]['children'][] = $quiz_post_id;
                    }
                }
            }
        }

        if ($this->is_course_quizzes_addon_active()) {
            // End of course quiz(zes).
            $quizzes = isset($steps['sfwd-quiz']) && is_array($steps['sfwd-quiz']) ? $steps['sfwd-quiz'] : [];

            foreach ($quizzes as $quiz_post_id => $quiz) {
                $sections[$key]['children'][] = $quiz_post_id;
            }
        }

        return $sections;
    }

    /**
     * Get the IDs of sections that exist from a previous migration, and delete sections that have been removed.
     *
     * @param  Section[] $course_sections The existing sections for the course.
     * @param  array     $uuids           The array of uuids for the sections that will be migrated.
     * @return array
     */
    protected function filter_existing_sections($course_sections, $uuids): array
    {
        $existing_sections = [];

        foreach ($course_sections as $existing_section) {
            if (in_array($existing_section->uuid, $uuids, true)) {
                $existing_sections[$existing_section->uuid] = $existing_section->id;
            } else {
                $existing_section->destroy();
            }
        }

        return $existing_sections;
    }

    /**
     * Parse the answer data for a LearnDash question and return an array of answer data.
     *
     * @param string $answer_data Serialized answer data.
     *
     * @return array The parsed answer data.
     */
    protected function parse_question_answer_data($answer_data): array
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        $answer_data = @unserialize($answer_data);
        $answers     = [];

        if (is_array($answer_data)) {
            foreach ($answer_data as $answer) {
                if ($answer instanceof WpProQuiz_Model_AnswerTypes) {
                    $answers[] = $answer->get_object_as_array();
                } elseif ($answer instanceof __PHP_Incomplete_Class) {
                    $values = [];

                    foreach ($answer as $k => $v) {
                        $values[$k] = $v;
                    }

                    $answers[] = $values;
                }
            }
        }

        return $answers;
    }

    /**
     * Convert the given fill in the blanks answer from LD format to MP format.
     *
     * @param string $answer The fill in the blanks answer.
     *
     * @return string The converted answer.
     */
    protected function convert_fill_blank_answer($answer): string
    {
        if (!is_string($answer)) {
            return '';
        }

        preg_match_all('#\{(.*?)}#im', $answer, $matches, PREG_SET_ORDER);

        foreach ($matches as $v) {
            $text    = $v[1];
            $answers = [];

            if (preg_match_all('#\[(.*?)]#im', $text, $multi_matches)) {
                foreach ($multi_matches[1] as $multi_text) {
                    if (strpos($multi_text, '|') !== false) {
                        list($multi_text) = explode('|', $multi_text); // Ignore per-answer points.
                    }

                    $answers[] = trim(html_entity_decode($multi_text, ENT_QUOTES));
                }
            } elseif (strpos($text, '|') !== false) {
                list($text) = explode('|', $text); // Ignore per-answer points.

                $answers[] = trim(html_entity_decode($text, ENT_QUOTES));
            } else {
                $answers[] = trim(html_entity_decode($text, ENT_QUOTES));
            }

            $pos = strpos($answer, $v[0]);

            if ($pos !== false) {
                $answer = substr_replace(
                    $answer,
                    sprintf('[%s]', implode(', ', $answers)),
                    $pos,
                    strlen($v[0])
                );
            }
        }

        return $answer;
    }

    /**
     * Convert the given Assessment answer to options and settings for the Likert Scale question in Courses.
     *
     * @param string $answer The assessment answer.
     *
     * @return array The options and settings for the Likert Scale question.
     */
    protected function convert_likert_scale_answer($answer)
    {
        $answer   = wp_strip_all_tags($answer);
        $options  = [];
        $settings = [
            'lowLabel'  => '',
            'highLabel' => '',
        ];

        if (!empty($answer)) {
            preg_match_all('#\{(.*?)}#im', $answer, $matches);

            if (!empty($matches[0][0]) && !empty($matches[1][0])) {
                $pos = strpos($answer, $matches[0][0]);

                $settings['lowLabel']  = trim(substr($answer, 0, $pos));
                $settings['highLabel'] = trim(substr($answer, $pos + strlen($matches[0][0])));

                preg_match_all('#\[([^|\]]+)]#im', $matches[1][0], $option_matches);

                if (!empty($option_matches[1])) {
                    foreach ($option_matches[1] as $option) {
                        $options[] = trim($option);
                    }
                }
            }
        }

        return [$options, $settings];
    }

    /**
     * Migrate user progress.
     *
     * @param array $data The data for the current request.
     */
    protected function migrate_progress(array $data)
    {
        self::before_start();

        list($limit, $offset) = $this->get_request_limit_offset($data, 10);

        $fetch_batch = function ($limit, $offset) {
            global $wpdb;

            $query = "SELECT
                  ua.user_id,
                  ua.activity_started,
                  ua.activity_completed,
                  p1.ID AS mp_lesson_id,
                  p3.ID AS mp_course_id,
                  p5.ID AS mp_parent_lesson_id
                FROM {$wpdb->prefix}learndash_user_activity ua
                INNER JOIN $wpdb->posts p1
                  ON p1.ID = (
                    SELECT p2.ID
                    FROM $wpdb->posts p2
                    INNER JOIN $wpdb->postmeta pm1 ON pm1.post_id = p2.ID AND pm1.meta_key = '_mepr_learndash_course_id'
                    INNER JOIN $wpdb->postmeta pm2 ON pm2.post_id = p2.ID AND pm2.meta_key = '_mepr_learndash_lesson_id'
                    WHERE p2.post_type = %s
                      AND p2.post_status = 'publish'
                      AND pm1.meta_value = ua.course_id
                      AND pm2.meta_value = ua.post_id
                    LIMIT 1
                  )
                INNER JOIN $wpdb->posts p3
                  ON p3.ID = (
                    SELECT p4.ID
                    FROM $wpdb->posts p4
                    INNER JOIN $wpdb->postmeta pm3 ON pm3.post_id = p4.ID AND pm3.meta_key = '_mepr_learndash_course_id'
                    WHERE p4.post_type = %s
                      AND p4.post_status = 'publish'
                      AND pm3.meta_value = ua.course_id
                    LIMIT 1
                  )
                LEFT JOIN $wpdb->posts p5
                  ON p5.ID = (
                    SELECT p6.ID
                    FROM $wpdb->posts p6
                    INNER JOIN $wpdb->postmeta pm4 ON pm4.post_id = p6.ID AND pm4.meta_key = '_mepr_learndash_lesson_id'
                    INNER JOIN $wpdb->postmeta pm5 ON pm4.meta_value = pm5.meta_value AND pm5.meta_key = 'lesson_id'
                    WHERE p6.post_type = %s
                      AND p6.post_status = 'publish'
                      AND pm5.post_id = ua.post_id
                    LIMIT 1
                  )
                WHERE ua.user_id > 0
                  AND ua.activity_type IN ('lesson', 'topic')
                  AND ua.activity_completed IS NOT NULL
                  AND ua.activity_completed > 0
                ORDER BY ua.activity_id ASC
                LIMIT %d OFFSET %d";

            $user_activity = $wpdb->get_results(
                $wpdb->prepare(
                    $query,
                    Lesson::$cpt,
                    Course::$cpt,
                    Lesson::$cpt,
                    $limit,
                    $offset
                )
            );

            return is_array($user_activity) ? $user_activity : [];
        };

        $processor = new MeprBatchMigrator($fetch_batch, $limit, $offset);

        while ($processor->next_batch()) {
            $user_activity = $processor->get_items();

            foreach ($user_activity as $activity) {
                $user_id    = (int) $activity->user_id;
                $lesson_ids = array_filter([(int) $activity->mp_lesson_id, (int) $activity->mp_parent_lesson_id]);

                foreach ($lesson_ids as $lesson_id) {
                    if (!UserProgress::has_completed_lesson($user_id, $lesson_id)) {
                        $user_progress               = new UserProgress();
                        $user_progress->lesson_id    = $lesson_id;
                        $user_progress->course_id    = (int) $activity->mp_course_id;
                        $user_progress->user_id      = $user_id;
                        $user_progress->created_at   = Utils::ts_to_mysql_date((int) $activity->activity_started);
                        $user_progress->completed_at = Utils::ts_to_mysql_date((int) $activity->activity_completed);
                        $user_progress->store();
                    }
                }
            }
        }

        self::finish();

        if ($processor->has_items()) {
            $this->send_success_response($data, [
                'step'   => 'progress',
                'offset' => $processor->get_offset(),
                'limit'  => $processor->get_limit(),
                'logs'   => $this->logs,
            ]);
        }

        if (!$this->is_course_quizzes_addon_active()) {
            update_option('mepr_migrator_learndash_completed', true);

            $this->send_success_response($data, [
                'status' => 'complete',
                'logs'   => $this->logs,
            ]);
        } else {
            $this->send_success_response($data, [
                'step' => 'attempts',
                'logs' => $this->logs,
            ]);
        }
    }

    /**
     * Migrate quiz attempts.
     *
     * @param array $data The data for the current request.
     */
    protected function migrate_attempts(array $data)
    {
        self::before_start();

        list($limit, $offset) = $this->get_request_limit_offset($data, 10);

        $fetch_batch = function ($limit, $offset) {
            global $wpdb;

            $query = "SELECT
                  ua.activity_id,
                  ua.user_id,
                  ua.activity_started,
                  ua.activity_completed,
                  p1.ID AS mp_lesson_id,
                  p3.ID AS mp_course_id,
                  uam.activity_meta_value AS score,
                  p5.ID as mp_parent_lesson_id
                FROM {$wpdb->prefix}learndash_user_activity ua
                INNER JOIN $wpdb->posts p1
                  ON p1.ID = (
                    SELECT p2.ID
                    FROM $wpdb->posts p2
                    INNER JOIN $wpdb->postmeta pm1 ON pm1.post_id = p2.ID AND pm1.meta_key = '_mepr_learndash_course_id'
                    INNER JOIN $wpdb->postmeta pm2 ON pm2.post_id = p2.ID AND pm2.meta_key = '_mepr_learndash_lesson_id'
                    WHERE p2.post_type = %s
                      AND p2.post_status = 'publish'
                      AND pm1.meta_value = ua.course_id
                      AND pm2.meta_value = ua.post_id
                    LIMIT 1
                  )
                INNER JOIN $wpdb->posts p3
                  ON p3.ID = (
                    SELECT p4.ID
                    FROM $wpdb->posts p4
                    INNER JOIN $wpdb->postmeta pm3 ON pm3.post_id = p4.ID AND pm3.meta_key = '_mepr_learndash_course_id'
                    WHERE p4.post_type = %s
                      AND p4.post_status = 'publish'
                      AND pm3.meta_value = ua.course_id
                    LIMIT 1
                 )
                INNER JOIN {$wpdb->prefix}learndash_user_activity_meta uam
                  ON uam.activity_meta_id = (
                    SELECT uam2.activity_meta_id
                    FROM {$wpdb->prefix}learndash_user_activity ua2
                    INNER JOIN {$wpdb->prefix}learndash_user_activity_meta uam2
                      ON uam2.activity_id = ua2.activity_id
                     AND uam2.activity_meta_key = 'percentage'
                    WHERE ua2.user_id = ua.user_id
                      AND ua2.post_id = ua.post_id
                      AND ua2.course_id = ua.course_id
                      AND ua2.activity_type = 'quiz'
                    ORDER BY CAST(uam2.activity_meta_value AS DECIMAL(10, 2)) DESC, ua2.activity_id DESC
                    LIMIT 1
                  )
                LEFT JOIN $wpdb->posts p5
                  ON p5.ID = (
                    SELECT p6.ID
                    FROM $wpdb->posts p6
                    INNER JOIN $wpdb->postmeta pm4 ON pm4.post_id = p6.ID AND pm4.meta_key = '_mepr_learndash_lesson_id'
                    INNER JOIN $wpdb->postmeta pm5 ON pm4.meta_value = pm5.meta_value AND pm5.meta_key = 'lesson_id'
                    WHERE p6.post_type = %s
                      AND p6.post_status = 'publish'
                      AND pm5.post_id = ua.post_id
                    LIMIT 1
                  )
                WHERE ua.user_id > 0
                  AND ua.activity_type = 'quiz'
                  AND ua.activity_completed IS NOT NULL
                  AND ua.activity_completed > 0
                  AND ua.activity_id = uam.activity_id
                ORDER BY ua.activity_id
                LIMIT %d OFFSET %d";

            $user_activity = $wpdb->get_results(
                $wpdb->prepare(
                    $query,
                    Quiz::$cpt,
                    Course::$cpt,
                    Lesson::$cpt,
                    $limit,
                    $offset
                )
            );

            return is_array($user_activity) ? $user_activity : [];
        };

        $processor      = new MeprBatchMigrator($fetch_batch, $limit, $offset);
        $grader_user_id = $this->get_grader_user_id();

        while ($processor->next_batch()) {
            $user_activity = $processor->get_items();

            foreach ($user_activity as $activity) {
                $user_id            = (int) $activity->user_id;
                $quiz_id            = (int) $activity->mp_lesson_id;
                $activity_started   = Utils::ts_to_mysql_date((int) $activity->activity_started);
                $activity_completed = Utils::ts_to_mysql_date((int) $activity->activity_completed);
                $lesson_ids         = array_filter([$quiz_id, (int) $activity->mp_parent_lesson_id]);

                foreach ($lesson_ids as $lesson_id) {
                    if (!UserProgress::has_completed_lesson($user_id, $lesson_id)) {
                        $user_progress               = new UserProgress();
                        $user_progress->lesson_id    = $lesson_id;
                        $user_progress->course_id    = (int) $activity->mp_course_id;
                        $user_progress->user_id      = $user_id;
                        $user_progress->created_at   = $activity_started;
                        $user_progress->completed_at = $activity_completed;
                        $user_progress->store();
                    }
                }

                $metadata = $this->get_activity_metadata((int) $activity->activity_id);
                $attempt  = Attempt::get_one([
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                ]);

                if (!$attempt instanceof Attempt) {
                    $attempt = new Attempt();
                }

                $attempt->quiz_id         = $quiz_id;
                $attempt->user_id         = $user_id;
                $attempt->status          = Attempt::$complete_str;
                $attempt->points_awarded  = (int) $metadata['points'] ?? 0;
                $attempt->points_possible = (int) $metadata['total_points'] ?? 0;
                $attempt->score           = round($metadata['percentage'] ?? 0);
                $attempt->started_at      = $activity_started;
                $attempt->finished_at     = $activity_completed;
                $result                   = $attempt->store();

                if ($result instanceof WP_Error || empty($attempt->id)) {
                    continue;
                }

                $statistic_ref_id = $metadata['statistic_ref_id'] ?? 0;

                if (empty($statistic_ref_id)) {
                    continue;
                }

                $migrated_questions = get_post_meta($quiz_id, '_mepr_learndash_migrated_questions', true);
                $migrated_questions = is_array($migrated_questions) ? $migrated_questions : [];

                global $wpdb;

                $answers = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            s.question_id,
                            s.answer_data,
                            s.points,
                            q.points AS points_possible,
                            q.answer_type
                        FROM {$wpdb->prefix}learndash_pro_quiz_statistic s
                        INNER JOIN {$wpdb->prefix}learndash_pro_quiz_question q
                        ON q.id = s.question_id
                        WHERE statistic_ref_id = %d
                        AND q.online = 1",
                        $statistic_ref_id
                    ),
                    ARRAY_A
                );

                foreach ($answers as $answer) {
                    $ld_question_id = $answer['question_id'];

                    if (!empty($migrated_questions[$ld_question_id])) {
                        $question_id = (int) $migrated_questions[$ld_question_id];
                    } else {
                        $question_id = 0;
                    }

                    if ($question_id) {
                        Answer::insert_or_replace_answer(
                            $attempt->id,
                            $question_id,
                            $this->get_answer_value($answer, $user_id, $question_id),
                            $answer['points_possible'],
                            $answer['points'],
                            $grader_user_id,
                            $attempt->finished_at,
                            $attempt->finished_at
                        );
                    }
                }
            }
        }

        self::finish();

        if ($processor->has_items()) {
            $this->send_success_response($data, [
                'step'   => 'attempts',
                'offset' => $processor->get_offset(),
                'limit'  => $processor->get_limit(),
                'logs'   => $this->logs,
            ]);
        }

        update_option('mepr_migrator_learndash_completed', true);

        $this->send_success_response($data, [
            'status' => 'complete',
            'logs'   => $this->logs,
        ]);
    }

    /**
     * Get metadata for a specific activity.
     *
     * @param  integer $activity_id The ID of the activity.
     * @return array              An array of metadata for the activity.
     */
    protected function get_activity_metadata(int $activity_id): array
    {
        global $wpdb;

        $query = "SELECT activity_meta_key, activity_meta_value
              FROM {$wpdb->prefix}learndash_user_activity_meta
              WHERE activity_id = %d";

        $metadata = $wpdb->get_results(
            $wpdb->prepare($query, $activity_id),
            ARRAY_A
        );

        return wp_list_pluck($metadata, 'activity_meta_value', 'activity_meta_key');
    }

    /**
     * Convert the answer from LD to MP format.
     *
     * @param array   $answer      The answer data.
     * @param integer $user_id     The user ID.
     * @param integer $question_id The question ID.
     *
     * @return array|string The converted answer value.
     */
    protected function get_answer_value(array $answer, int $user_id, int $question_id)
    {
        $answer_data = json_decode($answer['answer_data'], true);
        $value       = '';

        if (is_array($answer_data)) {
            switch ($answer['answer_type']) {
                case 'single':
                    $index = array_search(1, $answer_data);

                    if (is_int($index)) {
                        $value = (string) $index;
                    }
                    break;
                case 'multiple':
                    $question = $this->get_question($question_id);

                    if ($question instanceof Question && is_array($question->options)) {
                        $value = [];

                        foreach ($answer_data as $key => $selected) {
                            if ($selected && isset($question->options[$key])) {
                                $value[] = $question->options[$key];
                            }
                        }
                    }
                    break;
                case 'free_answer':
                case 'assessment_answer':
                    $value = $answer_data[0] ?? '';
                    break;
                case 'sort_answer':
                    $question = $this->get_question($question_id);

                    if ($question instanceof Question && is_array($question->options)) {
                        $updated = 0;

                        foreach ($question->options as $key => $value) {
                                $answer_hash = md5($user_id . $answer['question_id'] . $key);
                                $index       = array_search($answer_hash, $answer_data);

                            if (is_int($index)) {
                                $answer_data[$index] = $value;
                                $updated++;
                            }
                        }

                        if ($updated == count($answer_data)) {
                              $value = $answer_data;
                        }
                    }
                    break;
                case 'matrix_sort_answer':
                    $question = $this->get_question($question_id);

                    if ($question instanceof Question && is_array($question->options) && is_array($question->answer)) {
                        $updated = 0;

                        foreach ($question->options as $key => $value) {
                                $answer_hash = md5($user_id . $answer['question_id'] . $key);
                                $index       = array_search($answer_hash, $answer_data);

                            if (is_int($index) && isset($question->answer[$key])) {
                                $answer_data[$index] = $question->answer[$key];
                                $updated++;
                            }
                        }

                        if ($updated == count($answer_data)) {
                              $value = $answer_data;
                        }
                    }
                    break;
                case 'cloze_answer':
                    $value = $answer_data;
                    break;
                case 'essay':
                    $value = get_post_field('post_content', $answer_data['graded_id'] ?? 0, 'raw');
                    break;
            }
        }

        return $value;
    }

    /**
     * Get the default grader user ID.
     *
     * @return integer
     */
    protected function get_grader_user_id(): int
    {
        $admins = get_users([
            'role'    => 'administrator',
            'number'  => 1,
            'fields'  => 'ID',
            'orderby' => 'ID',
            'order'   => 'ASC',
        ]);

        return isset($admins[0]) ? (int) $admins[0] : 0;
    }

    /**
     * Get a question instance by ID.
     *
     * @param  integer $question_id The question ID.
     * @return Question|null
     */
    protected function get_question(int $question_id): ?Question
    {
        $question = wp_cache_get($question_id, 'mepr-migrator-question');

        if ($question instanceof Question) {
            return $question;
        }

        $question = new Question($question_id);

        if ($question->id > 0) {
            wp_cache_add($question_id, $question, 'mepr-migrator-question');

            return $question;
        }

        return null;
    }

    /**
     * Is the migration from LearnDash to MP Courses possible?
     *
     * Currently, this just checks for at least one existing LD Course and that the quiz table exists.
     *
     * @return boolean
     */
    public static function is_migration_possible(): bool
    {
        static $result;

        if (is_bool($result)) {
            return $result;
        }

        global $wpdb;
        $mepr_db = MeprDb::fetch();

        $ld_course_exists = (bool) $wpdb->get_var(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'sfwd-courses' LIMIT 1"
        );

        $ld_table_exists = $mepr_db->table_exists("{$wpdb->prefix}learndash_pro_quiz_master");

        $result = $ld_course_exists && $ld_table_exists;

        return $result;
    }

    /**
     * Is the Courses add-on active?
     *
     * @return boolean
     */
    protected function is_courses_addon_active(): bool
    {
        return defined('memberpress\\courses\\VERSION');
    }

    /**
     * Is the Course Quizzes add-on active?
     *
     * @return boolean
     */
    protected function is_course_quizzes_addon_active(): bool
    {
        return defined('memberpress\\quizzes\\VERSION');
    }
}
