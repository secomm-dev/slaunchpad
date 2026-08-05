<?php
namespace Magezon\Core\Model;

use Magento\Framework\Exception\LocalizedException;
use Magezon\Core\Framework\Controller\Result\JsonFactory;

class ExceptionHandler
{
    protected $_jsonFactory;

    public function __construct(
        JsonFactory $jsonFactory
    ) {
        $this->_jsonFactory = $jsonFactory;
    }

    /**
     * Converts an error to a response object.
     *
     * This iterates over all error codes and messages to change it into a flat
     * array. This enables simpler client behavior, as it is represented as a
     * list in JSON rather than an object/map.
     *
     * @since 5.7.0
     *
     * @param WP_Error $error WP_Error instance.
     *
     * @return WP_REST_Response List of associative arrays with code and message keys.
     */
    public function convertErrorToResponse( $error ) {
        if ($error instanceof \Magezon\Core\Exception\ErrorException) {
            $status = array_reduce(
                $error->get_all_error_data(),
                static function ( $status, $error_data ) {
                    return is_array( $error_data ) && isset( $error_data['status'] ) ? $error_data['status'] : $status;
                },
                500
            );

            $errors = array();

            foreach ( (array) $error->errors as $code => $messages ) {
                $all_data  = $error->get_all_error_data( $code );
                $last_data = array_pop( $all_data );

                foreach ( (array) $messages as $message ) {
                    $formatted = array(
                        'code'    => $code,
                        'message' => $message,
                        'data'    => $last_data,
                    );

                    if ( $all_data ) {
                        $formatted['additional_data'] = $all_data;
                    }

                    $errors[] = $formatted;
                }
            }

            $data = $errors[0];
            if ( count( $errors ) > 1 ) {
                // Remove the primary error.
                array_shift( $errors );
                $data['additional_errors'] = $errors;
            }
        } else if ($error instanceof LocalizedException) {
            $data['message'] = $error->getMessage();
        } else {
            $data['message'] = __('Something went wrong while processing the request.');
        }
        $resultJson = $this->_jsonFactory->create();
        $resultJson->setStatusHeader(
            \Laminas\Http\Response::STATUS_CODE_400,
            \Laminas\Http\AbstractMessage::VERSION_11,
            __('Bad Request')
        );
        return $resultJson->setData($data);
    }
}