<?php
/**
 * 9router Image Provider Class
 *
 * Tích hợp dịch vụ tạo ảnh 9router (Codex) qua OpenAI-compatible images/generations endpoint.
 *
 * @package AI_Image_Generator
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIIG_9Router_Image_Provider
 *
 * Gọi POST /v1/images/generations tới endpoint 9router,
 * nhận về b64_json và lưu thành file ảnh tạm thời.
 */
class AIIG_9Router_Image_Provider {

	// Timeout mặc định (giây).
	const API_TIMEOUT = 120;

	// OpenAI-compatible endpoint paths.
	const GENERATIONS_PATH = '/v1/images/generations';
	const MODELS_PATH      = '/v1/models';

	/**
	 * URL endpoint 9router (ví dụ: http://localhost:20128).
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Bearer token để xác thực.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name (ví dụ: cx/gpt-5.4-image).
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Kích thước ảnh (ví dụ: 1792x1024).
	 *
	 * @var string
	 */
	private $image_size;

	/**
	 * Chất lượng ảnh: auto | low | medium | high | standard | hd.
	 *
	 * @var string
	 */
	private $quality;

	/**
	 * Background handling: auto | transparent | opaque.
	 *
	 * @var string
	 */
	private $background;

	/**
	 * Image detail level: auto | low | high | original.
	 *
	 * @var string
	 */
	private $image_detail;

	/**
	 * Định dạng output: jpeg | png | webp.
	 *
	 * @var string
	 */
	private $output_format;

	/**
	 * Constructor.
	 *
	 * @param string $endpoint      URL gốc của 9router (không có dấu / cuối).
	 * @param string $api_key       Bearer token xác thực.
	 * @param string $model         Model name.
	 * @param string $image_size    Kích thước ảnh, ví dụ 1792x1024.
	 * @param string $quality       Chất lượng ảnh.
	 * @param string $output_format Định dạng: jpeg | png | webp.
	 * @param string $background    Background handling.
	 * @param string $image_detail  Image detail level.
	 *
	 * @throws Exception Nếu endpoint hoặc api_key rỗng.
	 */
	public function __construct(
		$endpoint,
		$api_key,
		$model = 'cx/gpt-5.4-image',
		$image_size = 'auto',
		$quality = 'auto',
		$output_format = 'png',
		$background = 'auto',
		$image_detail = 'high'
	) {
		if ( empty( $endpoint ) ) {
			throw new Exception( __( '9router endpoint không được để trống. Vui lòng cấu hình trong Settings.', 'ai-image-generator-congcuseoai' ) );
		}
		if ( empty( $api_key ) ) {
			throw new Exception( __( '9router API key không được để trống. Vui lòng cấu hình trong Settings.', 'ai-image-generator-congcuseoai' ) );
		}

		$this->endpoint      = $this->normalize_endpoint( $endpoint );
		$this->api_key       = $this->normalize_api_key( $api_key );
		$this->model         = $model;
		$this->image_size    = $image_size;
		$this->quality       = $quality;
		$this->background    = $background;
		$this->image_detail  = $image_detail;
		$this->output_format = $output_format;
	}

	/**
	 * Chuẩn hóa endpoint để chấp nhận cả base URL và full curl URL.
	 *
	 * @param string $endpoint Endpoint được nhập trong settings.
	 * @return string Endpoint gốc, không có route OpenAI-compatible phía sau.
	 */
	private function normalize_endpoint( $endpoint ) {
		$endpoint = rtrim( trim( $endpoint ), '/' );

		$known_suffixes = array(
			self::GENERATIONS_PATH,
			self::MODELS_PATH,
			'/v1',
		);

		foreach ( $known_suffixes as $suffix ) {
			$suffix_length = strlen( $suffix );

			if ( $suffix_length <= strlen( $endpoint ) && $suffix === substr( $endpoint, -$suffix_length ) ) {
				return rtrim( substr( $endpoint, 0, -$suffix_length ), '/' );
			}
		}

		return $endpoint;
	}

	/**
	 * Build API URL từ endpoint gốc và path cần gọi.
	 *
	 * @param string $path API path.
	 * @return string Full API URL.
	 */
	private function build_api_url( $path ) {
		return $this->endpoint . $path;
	}

	/**
	 * Chuẩn hóa API key nếu người dùng dán cả Authorization header.
	 *
	 * @param string $api_key API key hoặc chuỗi Bearer token.
	 * @return string Token không gồm tiền tố Bearer.
	 */
	private function normalize_api_key( $api_key ) {
		$api_key = trim( $api_key );

		if ( 0 === stripos( $api_key, 'Bearer ' ) ) {
			return trim( substr( $api_key, 7 ) );
		}

		return $api_key;
	}

	/**
	 * Tạo ảnh từ prompt.
	 *
	 * @param string $prompt Mô tả ảnh cần tạo.
	 * @return array Mảng gồm 'path' (đường dẫn file tạm) và 'prompt'.
	 * @throws Exception Nếu prompt rỗng, API lỗi hoặc response không hợp lệ.
	 */
	public function generate( $prompt ) {
		if ( empty( $prompt ) ) {
			throw new Exception( __( 'Prompt không được để trống.', 'ai-image-generator-congcuseoai' ) );
		}

		$api_url = $this->build_api_url( self::GENERATIONS_PATH );

		$body = array(
			'model'         => $this->model,
			'prompt'        => $prompt,
			'n'             => 1,
			'size'          => $this->image_size,
			'quality'       => $this->quality,
			'background'    => $this->background,
			'image_detail'  => $this->image_detail,
			'output_format' => $this->output_format,
		);

		$json_body = wp_json_encode( $body );
		if ( false === $json_body ) {
			throw new Exception( 'Lỗi JSON encode: ' . json_last_error_msg() );
		}

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'text/event-stream',
			),
			'body'    => $json_body,
			'timeout' => self::API_TIMEOUT,
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: HTTP error message */
					__( 'Lỗi kết nối tới 9router: %s', 'ai-image-generator-congcuseoai' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$error_data    = json_decode( $body, true );
			$error_message = $this->extract_error_message( $error_data, $body, $response );
			throw new Exception(
				sprintf(
					/* translators: 1: HTTP code, 2: endpoint URL, 3: error message */
					__( 'Lỗi từ 9router API (%1$d) tại %2$s: %3$s', 'ai-image-generator-congcuseoai' ),
					$code,
					$api_url,
					$error_message
				)
			);
		}
		$data = $this->parse_response_body( $body );

		if ( ! isset( $data['data'][0]['b64_json'] ) ) {
			// Kiểm tra có url không (một số API trả url thay vì b64_json).
			if ( isset( $data['data'][0]['url'] ) ) {
				throw new Exception( __( '9router trả về URL thay vì b64_json. Vui lòng kiểm tra cấu hình output_format.', 'ai-image-generator-congcuseoai' ) );
			}
			throw new Exception( __( 'API 9router không trả về dữ liệu ảnh (b64_json). Vui lòng kiểm tra model và thông số.', 'ai-image-generator-congcuseoai' ) );
		}

		$base64_data = $data['data'][0]['b64_json'];

		if ( empty( $base64_data ) ) {
			throw new Exception( __( 'Dữ liệu ảnh nhận được từ 9router bị rỗng.', 'ai-image-generator-congcuseoai' ) );
		}

		$file_path = $this->save_image( $base64_data );

		return array(
			'path'   => $file_path,
			'prompt' => $prompt,
		);
	}

	/**
	 * Trích xuất thông báo lỗi rõ hơn từ JSON, SSE/plain body hoặc HTTP reason.
	 *
	 * @param array|null $error_data Parsed JSON error data.
	 * @param string     $body       Raw response body.
	 * @param array      $response   WordPress HTTP response.
	 * @return string Error message.
	 */
	private function extract_error_message( $error_data, $body, $response ) {
		if ( is_array( $error_data ) ) {
			if ( isset( $error_data['error']['message'] ) ) {
				return sanitize_text_field( $error_data['error']['message'] );
			}

			if ( isset( $error_data['message'] ) ) {
				return sanitize_text_field( $error_data['message'] );
			}
		}

		$body_message = trim( wp_strip_all_tags( (string) $body ) );
		if ( '' !== $body_message ) {
			return sanitize_text_field( mb_substr( $body_message, 0, 300 ) );
		}

		$response_message = wp_remote_retrieve_response_message( $response );
		if ( ! empty( $response_message ) ) {
			return sanitize_text_field( $response_message );
		}

		return __( 'Lỗi API không xác định', 'ai-image-generator-congcuseoai' );
	}

	/**
	 * Kiểm tra kết nối tới 9router endpoint.
	 *
	 * @return bool True nếu kết nối thành công.
	 * @throws Exception Nếu kết nối thất bại.
	 */
	public function test_connection() {
		$api_url = $this->build_api_url( self::MODELS_PATH );

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
			),
			'timeout' => 15,
			'method'  => 'GET',
		);

		$response = wp_remote_get( $api_url, $args ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: error message */
					__( 'Không thể kết nối tới 9router endpoint: %s', 'ai-image-generator-congcuseoai' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 200 OK hoặc 401 Unauthorized đều nghĩa là endpoint hoạt động.
		if ( 200 === $code || 401 === $code ) {
			return true;
		}

		throw new Exception(
			sprintf(
				/* translators: %d: HTTP status code */
				__( '9router endpoint phản hồi mã lỗi HTTP: %d', 'ai-image-generator-congcuseoai' ),
				$code
			)
		);
	}

	/**
	 * Giải mã base64 và lưu ảnh vào thư mục tạm.
	 *
	 * @param string $base64_data Chuỗi base64 của ảnh.
	 * @return string Đường dẫn file ảnh đã lưu.
	 * @throws Exception Nếu không decode được hoặc không ghi được file.
	 */
	private function save_image( $base64_data ) {
		// Bóc tách data URI nếu có.
		$base64_data = preg_replace( '/^data:image\/\w+;base64,/', '', $base64_data );

		$image_data = base64_decode( $base64_data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_data ) {
			throw new Exception( __( 'Không thể giải mã dữ liệu ảnh từ 9router.', 'ai-image-generator-congcuseoai' ) );
		}

		$image_info = getimagesizefromstring( $image_data );
		if ( false === $image_info || empty( $image_info['mime'] ) ) {
			throw new Exception( __( 'Dữ liệu trả về từ 9router không phải là ảnh hợp lệ.', 'ai-image-generator-congcuseoai' ) );
		}

		// Xác định extension dựa theo output_format được cấu hình.
		$ext_map = array(
			'jpeg' => 'jpg',
			'jpg'  => 'jpg',
			'png'  => 'png',
			'webp' => 'webp',
		);
		$ext = isset( $ext_map[ $this->output_format ] ) ? $ext_map[ $this->output_format ] : 'jpg';

		// Fallback: detect từ binary header nếu output_format không rõ.
		if ( strlen( $image_data ) >= 4 ) {
			$header = substr( $image_data, 0, 4 );
			if ( "\xFF\xD8\xFF" === substr( $header, 0, 3 ) ) {
				$ext = 'jpg';
			} elseif ( "\x89PNG" === $header ) {
				$ext = 'png';
			} elseif ( 'RIFF' === substr( $header, 0, 4 ) ) {
				$ext = 'webp';
			}
		}

		$temp_dir  = $this->get_temp_dir();
		$temp_file = $temp_dir . wp_unique_id( 'aiig-9router-' ) . '.' . $ext;

		if ( ! $this->put_file_contents( $temp_file, $image_data ) ) {
			throw new Exception( __( 'Không thể ghi file ảnh tạm.', 'ai-image-generator-congcuseoai' ) );
		}

		return $temp_file;
	}

	/**
	 * Lấy (và tạo nếu chưa có) thư mục tạm để lưu ảnh.
	 *
	 * @return string Đường dẫn thư mục tạm (có dấu / cuối).
	 * @throws Exception Nếu không tạo hoặc không ghi được thư mục.
	 */
	private function get_temp_dir() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/aiig-temp/';

		if ( ! file_exists( $temp_dir ) ) {
			if ( ! wp_mkdir_p( $temp_dir ) ) {
				throw new Exception( 'Không thể tạo thư mục tạm: ' . $temp_dir );
			}
			$this->put_file_contents( $temp_dir . 'index.php', '<?php // Silence is golden' );
		}

		if ( ! is_writable( $temp_dir ) ) {
			throw new Exception( 'Thư mục tạm không có quyền ghi: ' . $temp_dir );
		}

		return $temp_dir;
	}

	/**
	 * Write a file using the WordPress filesystem API.
	 *
	 * @param string $file_path Destination file path.
	 * @param string $contents  File contents.
	 * @return bool True on success.
	 */
	private function put_file_contents( $file_path, $contents ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return false;
		}

		return (bool) $wp_filesystem->put_contents( $file_path, $contents, FS_CHMOD_FILE );
	}

	/**
	 * Parse either regular JSON or Server-Sent Events returned by 9router.
	 *
	 * @param string $body Raw HTTP response body.
	 * @return array Parsed response normalized to the images/generations shape.
	 * @throws Exception If no image payload can be found.
	 */
	private function parse_response_body( $body ) {
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $data ) ) {
			return $data;
		}

		$events = preg_split( '/\r\n|\r|\n/', $body );
		foreach ( $events as $line ) {
			$line = trim( $line );
			if ( 0 !== strpos( $line, 'data:' ) ) {
				continue;
			}

			$payload = trim( substr( $line, 5 ) );
			if ( '' === $payload || '[DONE]' === $payload ) {
				continue;
			}

			$event_data = json_decode( $payload, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $event_data ) ) {
				continue;
			}

			if ( isset( $event_data['data'][0]['b64_json'] ) || isset( $event_data['data'][0]['url'] ) ) {
				return $event_data;
			}

			$b64_json = $this->find_response_value( $event_data, 'b64_json' );
			if ( ! empty( $b64_json ) ) {
				return array(
					'data' => array(
						array(
							'b64_json' => $b64_json,
						),
					),
				);
			}

			$url = $this->find_response_value( $event_data, 'url' );
			if ( ! empty( $url ) ) {
				return array(
					'data' => array(
						array(
							'url' => $url,
						),
					),
				);
			}
		}

		throw new Exception(
			sprintf(
				/* translators: %s: JSON error message */
				__( 'Phản hồi JSON/SSE không hợp lệ từ 9router: %s', 'ai-image-generator-congcuseoai' ),
				json_last_error_msg()
			)
		);
	}

	/**
	 * Find a named scalar value recursively in an API response.
	 *
	 * @param array  $data Response data.
	 * @param string $key  Key to find.
	 * @return string Found value or empty string.
	 */
	private function find_response_value( $data, $key ) {
		foreach ( $data as $data_key => $value ) {
			if ( $key === $data_key && is_scalar( $value ) ) {
				return (string) $value;
			}

			if ( is_array( $value ) ) {
				$found = $this->find_response_value( $value, $key );
				if ( '' !== $found ) {
					return $found;
				}
			}
		}

		return '';
	}
}
