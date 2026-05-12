<?php
/**
 * Image Generator Class — Gemini Enterprise Agent Platform & 9router (Codex)
 *
 * @package AI_Image_Generator
 * @since   2.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIIG_Image_Generator
 *
 * Facade điều phối việc tạo ảnh sang Gemini Enterprise Agent Platform hoặc 9router
 * tuỳ theo cài đặt image_provider.
 */
class AIIG_Image_Generator {

	// API timeout in seconds.
	const API_TIMEOUT = 120;

	// Agent Platform image API defaults. Technical REST endpoints still use Vertex AI API.
	const VERTEX_TOKEN_TRANSIENT_TTL_BUFFER = 120;
	const VERTEX_OAUTH_SCOPE                = 'https://www.googleapis.com/auth/cloud-platform';
	const DEFAULT_GEMINI_IMAGE_MODEL        = 'gemini-3.1-flash-image-preview';

	// Default aspect ratios.
	const ASPECT_RATIO_SQUARE    = '1:1';
	const ASPECT_RATIO_LANDSCAPE = '16:9';
	const ASPECT_RATIO_PORTRAIT  = '9:16';

	/**
	 * Provider hiện đang dùng: 'gemini' | '9router'.
	 *
	 * @var string
	 */
	private $image_provider;

	/**
	 * Agent Platform service account JSON.
	 *
	 * @var string
	 */
	private $vertex_service_account_json;

	/**
	 * Gemini model name.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Agent Platform location.
	 *
	 * @var string
	 */
	private $vertex_location;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Back-compat placeholder, no longer used for Gemini image generation.
	 * @param string|null $model   Gemini model (tuỳ chọn, mặc định lấy từ Settings).
	 * @param string|null $vertex_service_account_json Agent Platform service account JSON.
	 * @param string|null $vertex_location Agent Platform location.
	 * @throws Exception Nếu provider Gemini được chọn nhưng thiếu Agent Platform service account JSON.
	 */
	public function __construct( $api_key = null, $model = null, $vertex_service_account_json = null, $vertex_location = null ) {
		$this->image_provider = AIIG_Settings::get_setting( 'image_provider', 'gemini' );

		// Khởi tạo Agent Platform fields (dù dùng 9router hay không, vẫn cần cho test_connection Gemini).
		$this->vertex_service_account_json = $vertex_service_account_json ?: AIIG_Settings::get_setting( 'vertex_service_account_json', '' );
		$this->vertex_location             = $vertex_location ?: AIIG_Settings::get_setting( 'vertex_location', 'global' );
		$this->model                       = $this->normalize_gemini_image_model( $model ?: AIIG_Settings::get_setting( 'gemini_model', self::DEFAULT_GEMINI_IMAGE_MODEL ) );

		// Chỉ bắt buộc service account JSON khi provider là Gemini.
		if ( 'gemini' === $this->image_provider && empty( $this->vertex_service_account_json ) ) {
			throw new Exception( __( 'Agent Platform service account JSON không được để trống. Vui lòng cấu hình trong Settings.', 'ai-image-generator-congcuseoai' ) );
		}
	}

	/**
	 * Generate image from text prompt.
	 *
	 * @param string $prompt Text description for the image.
	 * @return array Array with 'path' (temp file path) and 'prompt' (original prompt).
	 * @throws Exception If prompt is empty, API fails, or response is invalid.
	 */
	public function generate( $prompt ) {
		if ( empty( $prompt ) ) {
			throw new Exception( __( 'Prompt không được để trống', 'ai-image-generator-congcuseoai' ) );
		}

		// Routing sang 9router nếu được chọn.
		if ( '9router' === $this->image_provider ) {
			return $this->generate_via_9router( $prompt );
		}

		// Mặc định: Gemini Enterprise Agent Platform.
		return $this->generate_via_gemini( $prompt );
	}

	/**
	 * Delegate tạo ảnh sang AIIG_9Router_Image_Provider.
	 *
	 * @param string $prompt Prompt mô tả ảnh.
	 * @return array Kết quả từ provider.
	 * @throws Exception Nếu cấu hình thiếu hoặc API lỗi.
	 */
	private function generate_via_9router( $prompt ) {
		$endpoint      = AIIG_Settings::get_setting( '9router_endpoint', '' );
		$api_key       = AIIG_Settings::get_setting( '9router_api_key', '' );
		$model         = AIIG_Settings::get_setting( '9router_model', 'cx/gpt-5.4-image' );
		$image_size    = AIIG_Settings::get_setting( '9router_image_size', 'auto' );
		$quality       = AIIG_Settings::get_setting( '9router_quality', 'auto' );
		$background    = AIIG_Settings::get_setting( '9router_background', 'auto' );
		$image_detail  = AIIG_Settings::get_setting( '9router_image_detail', 'high' );
		$output_format = AIIG_Settings::get_setting( '9router_output_format', 'png' );

		$provider = new AIIG_9Router_Image_Provider(
			$endpoint,
			$api_key,
			$model,
			$image_size,
			$quality,
			$output_format,
			$background,
			$image_detail
		);

		return $provider->generate( $prompt );
	}

	/**
	 * Generate image via Gemini Enterprise Agent Platform.
	 *
	 * @param string $prompt Prompt mô tả ảnh.
	 * @return array Kết quả từ Gemini.
	 * @throws Exception Nếu API lỗi.
	 */
	private function generate_via_gemini( $prompt ) {
		$access_token = $this->get_vertex_access_token();
		$api_url      = $this->get_vertex_model_url( 'generateContent' );
		$body         = $this->build_vertex_generate_body( $prompt );
		$json_body    = wp_json_encode( $body );

		if ( false === $json_body ) {
			throw new Exception( 'Lỗi JSON encode: ' . json_last_error_msg() );
		}

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $json_body,
				'timeout' => self::API_TIMEOUT,
				'method'  => 'POST',
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Lỗi kết nối HTTP tới Agent Platform: ' . $response->get_error_message() );
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$body_response = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			throw new Exception(
				sprintf(
					/* translators: 1: HTTP status code, 2: API error message */
					__( 'Lỗi từ Agent Platform (%1$d): %2$s', 'ai-image-generator-congcuseoai' ),
					$code,
					$this->extract_google_error_message( $body_response )
				)
			);
		}

		$data        = json_decode( $body_response, true );
		$base64_data = $this->extract_vertex_image_data( $data );

		if ( empty( $base64_data ) ) {
			throw new Exception( __( 'Dữ liệu ảnh nhận được từ Agent Platform bị rỗng.', 'ai-image-generator-congcuseoai' ) );
		}

		$file_path = $this->save_base64_image( $base64_data );

		return array(
			'path'   => $file_path,
			'prompt' => $prompt,
		);
	} // end generate_via_gemini()

	/**
	 * Normalize Gemini image model and migrate legacy Imagen selections.
	 *
	 * @param string $model Model ID.
	 * @return string Supported Agent Platform Gemini image model ID.
	 */
	private function normalize_gemini_image_model( $model ) {
		$allowed_models = array(
			'gemini-3.1-flash-image-preview',
			'gemini-3-pro-image-preview',
		);

		return in_array( $model, $allowed_models, true ) ? $model : self::DEFAULT_GEMINI_IMAGE_MODEL;
	}

	/**
	 * Build Agent Platform generateContent request body for image generation.
	 *
	 * @param string $prompt Prompt mô tả ảnh.
	 * @return array Request body.
	 */
	private function build_vertex_generate_body( $prompt ) {
		return array(
			'contents'         => array(
				array(
					'role'  => 'USER',
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
			'generationConfig' => array(
				'responseModalities' => array( 'TEXT', 'IMAGE' ),
				'imageConfig'        => array(
					'aspectRatio' => $this->get_vertex_aspect_ratio(),
				),
			),
			'safetySettings'   => array(
				array(
					'method'    => 'PROBABILITY',
					'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
				),
			),
		);
	}

	/**
	 * Map existing image size options to Agent Platform aspect ratios.
	 *
	 * @return string Aspect ratio.
	 */
	private function get_vertex_aspect_ratio() {
		$image_size = AIIG_Settings::get_setting( 'image_size', '1024x1024' );

		switch ( $image_size ) {
			case '1792x1024':
				return '16:9';
			case '1024x1792':
				return '9:16';
			case '1408x1056':
				return '4:3';
			case '1056x1408':
				return '3:4';
			default:
				return '1:1';
		}
	}

	/**
	 * Build Agent Platform model method URL.
	 *
	 * @param string $method Model method, e.g. generateContent or countTokens.
	 * @return string API URL.
	 * @throws Exception Nếu service account JSON không hợp lệ.
	 */
	private function get_vertex_model_url( $method ) {
		$credentials = $this->get_vertex_credentials();
		$project_id  = rawurlencode( $credentials['project_id'] );
		$location    = sanitize_key( $this->vertex_location ?: 'global' );
		$model       = rawurlencode( $this->model );
		$host        = 'global' === $location ? 'https://aiplatform.googleapis.com' : 'https://' . $location . '-aiplatform.googleapis.com';

		return $host . '/v1/projects/' . $project_id . '/locations/' . rawurlencode( $location ) . '/publishers/google/models/' . $model . ':' . $method;
	}

	/**
	 * Decode and validate Agent Platform service account credentials.
	 *
	 * @return array Credentials.
	 * @throws Exception Nếu JSON không hợp lệ.
	 */
	private function get_vertex_credentials() {
		$credentials = json_decode( $this->vertex_service_account_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $credentials ) ) {
			throw new Exception( __( 'Agent Platform service account JSON không hợp lệ.', 'ai-image-generator-congcuseoai' ) );
		}

		$required_fields = array( 'project_id', 'private_key', 'client_email' );
		foreach ( $required_fields as $field ) {
			if ( empty( $credentials[ $field ] ) || ! is_string( $credentials[ $field ] ) ) {
				throw new Exception(
					sprintf(
						/* translators: %s: service account JSON field name */
						__( 'Agent Platform service account JSON thiếu trường bắt buộc: %s', 'ai-image-generator-congcuseoai' ),
						$field
					)
				);
			}
		}

		if ( empty( $credentials['token_uri'] ) ) {
			$credentials['token_uri'] = 'https://oauth2.googleapis.com/token';
		}

		return $credentials;
	}

	/**
	 * Get OAuth access token for Agent Platform using service account JWT flow.
	 *
	 * @return string OAuth access token.
	 * @throws Exception Nếu không lấy được token.
	 */
	private function get_vertex_access_token() {
		$credentials   = $this->get_vertex_credentials();
		$transient_key = 'aiig_vertex_token_' . md5( $credentials['client_email'] . '|' . $credentials['project_id'] );
		$cached_token  = get_transient( $transient_key );

		if ( is_string( $cached_token ) && '' !== $cached_token ) {
			return $cached_token;
		}

		$jwt      = $this->create_service_account_jwt( $credentials );
		$response = wp_remote_post(
			$credentials['token_uri'],
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 30,
				'method'  => 'POST',
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Lỗi kết nối OAuth Google: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || empty( $data['access_token'] ) ) {
			throw new Exception(
				sprintf(
					/* translators: 1: HTTP status code, 2: API error message */
					__( 'Không lấy được access token Agent Platform (%1$d): %2$s', 'ai-image-generator-congcuseoai' ),
					$code,
					$this->extract_google_error_message( $body )
				)
			);
		}

		$expires_in = isset( $data['expires_in'] ) ? max( 300, intval( $data['expires_in'] ) - self::VERTEX_TOKEN_TRANSIENT_TTL_BUFFER ) : 3300;
		set_transient( $transient_key, sanitize_text_field( $data['access_token'] ), $expires_in );

		return sanitize_text_field( $data['access_token'] );
	}

	/**
	 * Create signed JWT assertion for Google service account OAuth.
	 *
	 * @param array $credentials Service account credentials.
	 * @return string Signed JWT.
	 * @throws Exception Nếu OpenSSL hoặc private key không hợp lệ.
	 */
	private function create_service_account_jwt( $credentials ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			throw new Exception( __( 'PHP OpenSSL extension là bắt buộc để xác thực Agent Platform service account.', 'ai-image-generator-congcuseoai' ) );
		}

		$issued_at = time();
		$header    = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$claim     = array(
			'iss'   => $credentials['client_email'],
			'scope' => self::VERTEX_OAUTH_SCOPE,
			'aud'   => $credentials['token_uri'],
			'iat'   => $issued_at,
			'exp'   => $issued_at + 3600,
		);

		$encoded_header = wp_json_encode( $header );
		$encoded_claim  = wp_json_encode( $claim );

		if ( false === $encoded_header || false === $encoded_claim ) {
			throw new Exception( __( 'Không thể tạo JWT cho Agent Platform service account.', 'ai-image-generator-congcuseoai' ) );
		}

		$signing_input = $this->base64url_encode( $encoded_header ) . '.' . $this->base64url_encode( $encoded_claim );
		$signature     = '';
		$private_key   = str_replace( '\n', "\n", $credentials['private_key'] );

		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			throw new Exception( __( 'Không thể ký JWT bằng private key của Agent Platform service account.', 'ai-image-generator-congcuseoai' ) );
		}

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $data Raw data.
	 * @return string Base64url-encoded data.
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Extract first image payload from Agent Platform generateContent response.
	 *
	 * @param array|null $data Parsed response.
	 * @return string Base64 image data.
	 * @throws Exception Nếu response không có ảnh.
	 */
	private function extract_vertex_image_data( $data ) {
		if ( ! is_array( $data ) || empty( $data['candidates'][0] ) ) {
			throw new Exception( __( 'Cấu trúc phản hồi từ Agent Platform không hợp lệ.', 'ai-image-generator-congcuseoai' ) );
		}

		$candidate = $data['candidates'][0];
		if ( isset( $candidate['finishReason'] ) && 'STOP' !== $candidate['finishReason'] ) {
			if ( 'SAFETY' === $candidate['finishReason'] ) {
				throw new Exception( __( 'Ảnh bị chặn bởi bộ lọc an toàn của Agent Platform. Hãy thử thay đổi prompt nhẹ nhàng hơn.', 'ai-image-generator-congcuseoai' ) );
			}

			throw new Exception(
				sprintf(
					/* translators: %s: Agent Platform finish reason */
					__( 'Agent Platform dừng tạo ảnh. Lý do: %s', 'ai-image-generator-congcuseoai' ),
					sanitize_text_field( $candidate['finishReason'] )
				)
			);
		}

		$parts = $candidate['content']['parts'] ?? array();
		foreach ( $parts as $part ) {
			if ( ! empty( $part['inlineData']['data'] ) ) {
				return $part['inlineData']['data'];
			}

			if ( ! empty( $part['inline_data']['data'] ) ) {
				return $part['inline_data']['data'];
			}
		}

		throw new Exception( __( 'Agent Platform trả về thành công nhưng không có dữ liệu ảnh.', 'ai-image-generator-congcuseoai' ) );
	}

	/**
	 * Extract a readable Google API error message.
	 *
	 * @param string $body Raw response body.
	 * @return string Error message.
	 */
	private function extract_google_error_message( $body ) {
		$data = json_decode( $body, true );

		if ( is_array( $data ) ) {
			if ( ! empty( $data['error']['message'] ) ) {
				return sanitize_text_field( $data['error']['message'] );
			}

			if ( ! empty( $data['error_description'] ) ) {
				return sanitize_text_field( $data['error_description'] );
			}

			if ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
				return sanitize_text_field( $data['error'] );
			}
		}

		$body_message = trim( wp_strip_all_tags( (string) $body ) );
		return '' !== $body_message ? sanitize_text_field( mb_substr( $body_message, 0, 300 ) ) : __( 'Lỗi API không xác định', 'ai-image-generator-congcuseoai' );
	}

	/**
	 * Decode Gemini base64 image data and save it to a temporary uploads file.
	 *
	 * @param string $base64_data Base64-encoded image.
	 * @return string Temporary file path.
	 * @throws Exception When the image data is invalid or cannot be written.
	 */
	private function save_base64_image( $base64_data ) {
		$base64_data = preg_replace( '/^data:image\/\w+;base64,/', '', $base64_data );
		$image_data  = base64_decode( $base64_data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $image_data ) {
			throw new Exception( __( 'Không thể giải mã dữ liệu ảnh từ Gemini.', 'ai-image-generator-congcuseoai' ) );
		}

		$image_info = getimagesizefromstring( $image_data );
		if ( false === $image_info || empty( $image_info['mime'] ) ) {
			throw new Exception( __( 'Dữ liệu trả về từ Gemini không phải là ảnh hợp lệ.', 'ai-image-generator-congcuseoai' ) );
		}

		$extension_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);

		if ( ! isset( $extension_map[ $image_info['mime'] ] ) ) {
			throw new Exception( __( 'Định dạng ảnh Gemini trả về không được hỗ trợ.', 'ai-image-generator-congcuseoai' ) );
		}

		$temp_file = $this->get_temp_dir() . wp_unique_id( 'aiig-gemini-' ) . '.' . $extension_map[ $image_info['mime'] ];

		if ( ! $this->put_file_contents( $temp_file, $image_data ) ) {
			throw new Exception( __( 'Không thể ghi file ảnh tạm.', 'ai-image-generator-congcuseoai' ) );
		}

		return $temp_file;
	}
    
	/**
	 * Lấy (và tạo nếu chưa có) thư mục tạm để lưu ảnh Gemini.
	 *
	 * @return string Đường dẫn thư mục tạm (có dấu / cuối).
	 * @throws Exception Nếu không tạo hoặc không ghi được thư mục.
	 */
	private function get_temp_dir() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/aiig-temp/';

		if ( ! file_exists( $temp_dir ) ) {
			if ( ! wp_mkdir_p( $temp_dir ) ) {
				throw new Exception( 'Failed to create temp directory: ' . $temp_dir );
			}
			$this->put_file_contents( $temp_dir . 'index.php', '<?php // Silence is golden' );
		}

		if ( ! is_writable( $temp_dir ) ) {
			throw new Exception( 'Temp directory is not writable: ' . $temp_dir );
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
	 * Test API connection.
	 *
	 * Route sang 9router hoặc Gemini tuỳ provider đang dùng.
	 *
	 * @return bool True nếu kết nối thành công.
	 * @throws Exception Nếu kết nối thất bại.
	 */
	public function test_connection() {
		if ( '9router' === $this->image_provider ) {
			$endpoint = AIIG_Settings::get_setting( '9router_endpoint', '' );
			$api_key  = AIIG_Settings::get_setting( '9router_api_key', '' );
			$provider = new AIIG_9Router_Image_Provider( $endpoint, $api_key );
			return $provider->test_connection();
		}

		// Gemini Enterprise Agent Platform: kiểm tra OAuth và countTokens, không tạo ảnh test để tránh chi phí ảnh.
		try {
			$access_token = $this->get_vertex_access_token();
			$api_url      = $this->get_vertex_model_url( 'countTokens' );
			$body         = array(
				'contents' => array(
					array(
						'role'  => 'USER',
						'parts' => array(
							array( 'text' => 'Connection test' ),
						),
					),
				),
			);

			$response = wp_remote_post(
				$api_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 30,
					'method'  => 'POST',
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				throw new Exception(
					sprintf(
						/* translators: 1: HTTP status code, 2: API error message */
						__( 'Agent Platform countTokens lỗi (%1$d): %2$s', 'ai-image-generator-congcuseoai' ),
						$code,
						$this->extract_google_error_message( wp_remote_retrieve_body( $response ) )
					)
				);
			}

			return true;

		} catch ( Exception $e ) {
			throw new Exception( 'Gemini Enterprise Agent Platform: ' . $e->getMessage() );
		}
	}
}
