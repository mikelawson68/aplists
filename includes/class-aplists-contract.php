<?php
/**
 * apLists domain contract.
 *
 * @package apLists
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APLists_Contract')) {
    final class APLists_Contract
    {
        /**
         * @return array<string,mixed>
         */
        public static function component(): array
        {
            return [
                'key' => 'aplists',
                'name' => 'apLists',
                'layer' => 'standalone_plugin',
                'status' => 'commercial_1_0',
                'version' => APLISTS_VERSION,
                'responsibility' => 'WordPress subscriber list management, segment engine, contact import/export, CRM contact bridge, and list hygiene.',
                'stands_alone' => true,
                'theme_boundary' => self::theme_boundary_contract(),
                'depends_on' => [],
                'optional_integrations' => ['apCRM', 'apCampaignStudio', 'apForms', 'shared_settings_registry'],
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function domain(): array
        {
            return [
                'owns' => [
                    'subscriber_lists',
                    'list_segments',
                    'segment_criteria',
                    'subscribers',
                    'subscriber_fields',
                    'import_jobs',
                    'export_jobs',
                    'suppression_records',
                    'bounce_records',
                    'complaint_records',
                    'list_hygiene_rules',
                    'hygiene_jobs',
                ],
                'does_not_own' => [
                    'CRM contact truth',
                    'form submission truth',
                    'campaign creative truth',
                    'campaign delivery state',
                    'billing invoice truth',
                    'payment truth',
                ],
                'capabilities' => [
                    'subscriber_list_management',
                    'segmentation_engine',
                    'contact_import_export',
                    'crm_contact_bridge',
                    'list_hygiene',
                    'suppression_management',
                    'bounce_complaint_processing',
                    'field_mapping',
                    'merge_tag_support',
                    'double_opt_in',
                    'unsubscribe_processing',
                ],
                'integration_contract' => self::integration_contract(),
                'configuration_contract' => self::configuration_contract(),
                'hard_rules' => self::hard_rules(),
                'table_plan' => self::table_plan(),
            ];
        }

        /**
         * @return array<string,string>
         */
        public static function integration_contract(): array
        {
            return [
                'apCRM' => 'May sync subscriber data with CRM contacts and receive contact updates.',
                'apCampaignStudio' => 'Provides list and segment consumption for campaign targeting.',
                'apForms' => 'May receive form submission data for list enrollment.',
                'shared_settings_registry' => 'May expose shared list management settings.',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function configuration_contract(): array
        {
            return [
                'resolution_order' => ['shared_pubmotor_or_apcrm_settings', 'aplists_local_settings', 'wordpress_defaults'],
                'managed_settings_behavior' => [
                    'Shared suite list settings should render as managed/readonly in apLists UI.',
                    'Standalone apLists allows local list, segment, and hygiene setup.',
                ],
                'shared_setting_candidates' => ['default_double_opt_in', 'default_list_hygiene_policy', 'suppression_policy'],
            ];
        }

        /**
         * @return array<int,string>
         */
        public static function hard_rules(): array
        {
            return [
                'apLists must work on plain WordPress without apCRM.',
                'List hygiene rules must be configurable and auditable.',
                'Suppression, bounce, and complaint records must be processed automatically.',
                'Double opt-in workflow must be supported with confirmation emails.',
                'Unsubscribe requests must be processed immediately.',
                'No raw contact data may be stored in WordPress theme files.',
            ];
        }

        /**
         * @return array<string,string>
         */
        public static function table_plan(): array
        {
            return [
                'subscriber_lists' => 'aplists_subscriber_lists',
                'list_segments' => 'aplists_list_segments',
                'segment_criteria' => 'aplists_segment_criteria',
                'subscribers' => 'aplists_subscribers',
                'subscriber_fields' => 'aplists_subscriber_fields',
                'import_jobs' => 'aplists_import_jobs',
                'export_jobs' => 'aplists_export_jobs',
                'suppression_records' => 'aplists_suppression_records',
                'bounce_records' => 'aplists_bounce_records',
                'complaint_records' => 'aplists_complaint_records',
                'hygiene_rules' => 'aplists_hygiene_rules',
                'hygiene_jobs' => 'aplists_hygiene_jobs',
            ];
        }

        /**
         * @return array<string,mixed>
         */
        public static function theme_boundary_contract(): array
        {
            return [
                'themes_are_presentation_only' => true,
                'plugin_owned_surfaces' => ['data', 'schema', 'routes', 'permissions', 'workflow_state', 'integrations', 'business_rules'],
                'theme_owned_surfaces' => ['visual_rendering', 'CSS', 'template_slot_markup'],
                'hard_rules' => [
                    'No list management, segment criteria, subscriber data, or hygiene rules may live in a WordPress theme.',
                    'Theme failures may make a list management screen ugly; they must not make subscriber data wrong.',
                ],
            ];
        }
    }
}
