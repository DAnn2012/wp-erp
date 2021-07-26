<?php

namespace WeDevs\ERP\Settings;

use WeDevs\ERP\Framework\Traits\Ajax as Trait_Ajax;
use WeDevs\ERP\Framework\Traits\Hooker;

/**
 * Ajax handler class
 * 
 * @since 1.9.0
 */
class Ajax {

    use Trait_Ajax;
    use Hooker;

    /**
     * Bind all the ajax event for Framework
     *
     * @since 1.9.0
     *
     * @return void
     */
    public function __construct () {
        // Common settings
        $this->action( 'wp_ajax_erp-settings-save', 'erp_settings_save' );
        $this->action( 'wp_ajax_erp-settings-get-data', 'erp_settings_get_data' );
        
        // Email templates settings
        $this->action( 'wp_ajax_erp_get_email_templates', 'get_email_templates' );
        $this->action( 'wp_ajax_erp_get_single_email_template', 'get_single_email_template' );
        $this->action( 'wp_ajax_erp_update_email_status', 'update_email_status' );
        $this->action( 'wp_ajax_erp_update_email_template', 'update_email_template' );
        $this->action( 'wp_ajax_erp_smtp_test_connection', 'smtp_test_connection' );
    }

    /**
     * Save Settings Data
     *
     * @since 1.9.0
     *
     * @return void
     */
    public function erp_settings_save() {
        $this->verify_nonce( 'erp-settings-nonce' );

        $has_not_permission = ! current_user_can( 'manage_options' );
        $module             = ! empty( $_REQUEST['module'] )          ? sanitize_text_field( wp_unslash( $_REQUEST['module'] ) )          : '';
        $section            = ! empty( $_REQUEST['section'] )         ? sanitize_text_field( wp_unslash( $_REQUEST['section'] ) )         : '';
        $sub_section        = ! empty( $_REQUEST['sub_sub_section'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['sub_sub_section'] ) ) : '';

        switch ( $module ) {
            case 'general':
                $settings           = ( new General() );
                break;

            case 'erp-hr':
                $settings           = ( new \WeDevs\ERP\HRM\Settings() );
                $has_not_permission = $has_not_permission && ! current_user_can( 'erp_hr_manager' );
                break;

            case 'erp-ac':
                $settings           = ( new \WeDevs\ERP\Accounting\Includes\Classes\Settings() );
                $has_not_permission = $has_not_permission && ! current_user_can( 'erp_ac_manager' );
                break;

            case 'erp-crm':
                $settings           = ( new \WeDevs\ERP\CRM\CRM_Settings() );
                $has_not_permission = $has_not_permission && ! current_user_can( 'erp_crm_manager' );
                break;

            case 'erp-email':
                $settings           = new Email();
                break;

            case 'erp-integration':
                $settings           = new Integration();
                break;

            default:
                $settings           = apply_filters( "erp_settings_save_{$module}_section", $module, $section, $sub_section );
        }

        if ( $has_not_permission ) {
            $this->send_error( erp_get_message ( ['type' => 'error_permission'] ) );
        }

        $result = $settings->save( $section );

        if ( is_wp_error( $result ) ) {
            $this->send_error( $result->get_error_message() );
        }

        $this->send_success( [
            'message' => erp_get_message( [ 'type' => 'save_success', 'additional' => 'Settings' ] )
        ] );
    }

    /**
     * Get Settings Data For Common Sections
     *
     * @since 1.9.0
     *
     * @return void
     */
    public function erp_settings_get_data() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( erp_get_message( ['type' => 'error_permission'] ) );
        }

        $data = Helpers::process_settings_data( $_POST );

        if ( is_wp_error( $data ) ) {
            $this->send_error( erp_get_message( ['type' => 'error_process'] ) );
        }

        $this->send_success( $data );
    }


    /**
     * Retrieves all email templates
     * 
     * @since 1.9.0
     *
     * @return mixed
     */
    public function get_email_templates() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( _( 'You do not have sufficient permission to do this action', 'erp' ) );
        }

        $email_templates     = wperp()->emailer->get_emails();
        $emails              = [];
        
        $can_not_be_disabled = Helpers::get_fixedly_enabled_email_templates();

        foreach ( $email_templates as $key => $email ) {
            $email_option    = $email->get_option_id();
            $option_value    = get_option( $email_option );
            $disable_allowed = true;
            $is_enabled      = 'no';

            if ( in_array( $email_option, $can_not_be_disabled ) ) {
                $disable_allowed = false;
            }

            if ( isset( $option_value['is_enable'] ) ) {
                $is_enabled = 'yes';
            }

            $email_data = [
                'id'              => $key,
                'option_id'       => $email_option,   
                'name'            => esc_html( $email->get_title() ),
                'description'     => esc_html( $email->get_description() ),
                'is_enabled'      => $is_enabled,
                'disable_allowed' => $disable_allowed
            ];

            if (
                false !== strpos( get_class( $email ), 'HRM' ) ||
                false !== strpos( get_class( $email ), 'ERP_Document' ) ||
                false !== strpos( get_class( $email ), 'ERP_Recruitment' ) ||
                false !== strpos( get_class( $email ), 'Training' )
            ) {
                $emails['hrm'][] = $email_data;
            } else if ( false !== strpos( get_class( $email ), 'CRM' ) ) {
                $emails['crm'][] = $email_data;
            } else if ( false !== strpos( get_class( $email ), 'Accounting' ) ) {
                $emails['acct'][] = $email_data;
            }
        }

        $this->send_success( $emails );
    }

    /**
     * Retrieves a single email template
     * 
     * @since 1.9.0
     *
     * @return mixed
     */
    public function get_single_email_template() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( _( 'You do not have sufficient permission to do this action', 'erp' ) );
        }

        $can_not_be_disabled = Helpers::get_fixedly_enabled_email_templates();

        $template                      = ! empty( $_REQUEST['template'] ) ? sanitize_text_field( $_REQUEST['template'] ) : '';
        $email                         = wperp()->emailer->get_email( $template );
        $option_id                     = $email->get_option_id();
        $email_data                    = get_option( $option_id );
        $email_data['id']              = $option_id;
        $email_data['tags']            = [];
        $email_data['disable_allowed'] = true;

        if ( in_array( $option_id, $can_not_be_disabled ) ) {
            $email_data['disable_allowed'] = false;
        }

        if ( empty( $email_data['is_enable'] ) ) {
            $email_data['is_enable'] = 'no';
        }
        
        foreach( $email->find as $key => $find ) {
            $email_data['tags'][] = $find;
        }

        $email_data['body'] = str_replace( "\n", '<br>', $email_data['body'] );

        $this->send_success( $email_data );
    }

    /**
     * Updates email template
     * 
     * @since 1.9.0
     *
     * @return mixed
     */
    public function update_email_template() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( _( 'You do not have sufficient permission to do this action', 'erp' ) );
        }

        $email_id   = ! empty( $_REQUEST['id'] )        ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) )        : '';
        $is_enabled = ! empty( $_REQUEST['is_enable'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['is_enable'] ) ) : '';

        if ( empty( $email_id ) ) {
            $this->send_error( __( 'Invalid email template ID', 'erp' ) );
        }

        $option_data = [
            'subject' => ! empty( $_REQUEST['subject'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['subject'] ) )  : '',
            'heading' => ! empty( $_REQUEST['heading'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['heading'] ) )  : '',
            'body'    => ! empty( $_REQUEST['body'] )    ? wp_kses_post( wp_unslash( $_REQUEST['body'] ) )            : '',
        ];

        if ( 'yes' === $is_enabled ) {
            $option_data['is_enable'] = 'yes';
        }

        update_option( $email_id, $option_data );

        $this->send_success( __( 'Template updated successfully', 'erp' ) );
    }

    /**
     * Updates email status (enable/disable)
     * 
     * @since 1.9.0
     *
     * @return mixed
     */
    public function update_email_status() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( _( 'You do not have sufficient permission to do this action', 'erp' ) );
        }

        $option_id    = ! empty( $_REQUEST['option_id'] ) ? sanitize_text_field( $_REQUEST['option_id'] ) : '';
        $option_value = ! empty( $_REQUEST['option_value'] ) ? sanitize_text_field( $_REQUEST['option_value'] ) : '';

        if ( ! empty( $option_id ) ) {
            $email_option = get_option( $option_id );

            if ( 'yes' === $option_value ) {
                $email_option['is_enable'] = 'yes';
            } else {
                unset( $email_option['is_enable'] );
            }

            update_option( $option_id, $email_option );
        }

        $this->send_success();
    }

    /**
     * Test connection using SMTP credentials
     * 
     * @since 1.9.0
     *
     * @return mixed
     */
    public function smtp_test_connection() {
        $this->verify_nonce( 'erp-settings-nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->send_error( erp_get_message( ['type' => 'error_permission'] ) );
        }

        if ( empty( $_REQUEST['mail_server'] ) ) {
            $this->send_error( [ 'message' => esc_html__( 'No host address provided', 'erp' ) ] );
        } else {
            $mail_server = sanitize_text_field( wp_unslash( $_REQUEST['mail_server'] ) );
        }

        if ( empty( $_REQUEST['port'] ) ) {
            $this->send_error( [ 'message' => esc_html__( 'No port address provided', 'erp' ) ] );
        } else {
            $port = sanitize_text_field( wp_unslash( $_REQUEST['port'] ) );
        }

        if ( ! empty( $_REQUEST['authentication'] ) ) {
            $authentication = sanitize_text_field( wp_unslash( $_REQUEST['authentication'] ) );

            if ( empty( $_REQUEST['username'] ) ) {
                $this->send_error( [ 'message' => esc_html__( 'No email address provided', 'erp' ) ] );
            } else {
                $username = sanitize_text_field( wp_unslash( $_REQUEST['username'] ) );
            }

            if ( empty( $_REQUEST['password'] ) ) {
                $this->send_error( [ 'message' => esc_html__( 'No email password provided', 'erp' ) ] );
            } else {
                $password = sanitize_text_field( wp_unslash( $_REQUEST['password'] ) );
            }
        }

        if ( empty( $_REQUEST['test_email'] ) ) {
            $to = get_option( 'admin_email' );
        } else {
            $to = sanitize_text_field( wp_unslash( $_REQUEST['test_email'] ) );
        }

        global $phpmailer, $wp_version;

        // (Re)create it, if it's gone missing.
        if ( version_compare( $wp_version, '5.5' ) >= 0 ) {
            if ( ! ( $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer ) ) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $phpmailer = new \PHPMailer\PHPMailer\PHPMailer( true );
            }
        } else {
            if ( ! ( $phpmailer instanceof PHPMailer ) ) {
                require_once ABSPATH . WPINC . '/class-phpmailer.php';
                require_once ABSPATH . WPINC . '/class-smtp.php';
                $phpmailer = new \PHPMailer( true );
            }
        }

        $subject = esc_html__( 'ERP SMTP Test Mail', 'erp' );
        $message = esc_html__( 'This is a test email by WP ERP.', 'erp' );

        $erp_email_settings = get_option( 'erp_settings_erp-email_general', [] );

        if ( ! isset( $erp_email_settings['from_email'] ) ) {
            $from_email = get_option( 'admin_email' );
        } else {
            $from_email = $erp_email_settings['from_email'];
        }

        if ( ! isset( $erp_email_settings['from_name'] ) ) {
            global $current_user;

            $from_name = $current_user->display_name;
        } else {
            $from_name = $erp_email_settings['from_name'];
        }

        $content_type = 'text/html';

        $phpmailer->AddAddress( $to );
        $phpmailer->From       = $from_email;
        $phpmailer->FromName   = $from_name;
        $phpmailer->Sender     = $phpmailer->From;
        $phpmailer->Subject    = $subject;
        $phpmailer->Body       = $message;
        $phpmailer->Mailer     = 'smtp';
        $phpmailer->Host       = $mail_server;
        $phpmailer->SMTPSecure = $authentication;
        $phpmailer->Port       = $port;

        if ( ! empty( $_REQUEST['authentication'] ) ) {
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = $username;
            $phpmailer->Password   = $password;
        }

        $phpmailer->isHTML( true );

        try {
            $result = $phpmailer->Send();

            $this->send_success( [ 'message' => esc_html__( 'Test email has been sent successfully to ', 'erp' ) . $to ] );
        } catch ( \Exception $e ) {
            $this->send_error( $e->getMessage() );
        }
    }
}