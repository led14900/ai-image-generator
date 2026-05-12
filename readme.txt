=== AI Image Generator by CongCuSEOAI ===
Contributors: phamngoctu
Donate link: https://airender.vn
Tags: ai, image generator, gemini, vertex ai, openai, 9router, media
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tạo prompt và ảnh minh họa bằng AI cho bài viết WordPress, tối ưu ảnh và upload vào Media Library.

== Description ==

AI Image Generator by CongCuSEOAI giúp tạo ảnh minh họa cho bài viết WordPress. Plugin phân tích tiêu đề, tóm tắt và nội dung bài viết để tạo prompt bằng OpenAI hoặc Gemini, sau đó tạo ảnh bằng Gemini Enterprise Agent Platform (Vertex AI) hoặc endpoint 9router/OpenAI-compatible do quản trị viên cấu hình.

= Tính Năng Chính =

* Tạo 1-5 prompt từ nội dung bài viết.
* Cho phép chỉnh sửa prompt trước khi tạo ảnh.
* Hỗ trợ prompt providers: OpenAI và Google Gemini.
* Hỗ trợ image providers: Gemini Enterprise Agent Platform (Vertex AI) và 9router/OpenAI-compatible endpoint.
* Resize, nén ảnh và tùy chọn chuyển đổi WebP.
* Upload ảnh đã tạo vào WordPress Media Library.
* Lưu danh sách ảnh đã tạo trong post meta để hiển thị lại trong editor.

= Yêu Cầu =

* WordPress 5.0 trở lên.
* PHP 7.4 trở lên.
* GD Library hoặc Imagick extension.
* API key hợp lệ từ provider được sử dụng.

= Cách Sử Dụng =

1. Cài đặt và kích hoạt plugin.
2. Vào Media > AI Image Generator để cấu hình API keys và provider.
3. Mở màn hình tạo/sửa bài viết và tìm meta box "AI Image Generator".
4. Chọn số lượng prompt và bấm "Tạo Prompts".
5. Chỉnh prompt nếu cần rồi bấm "Tạo Ảnh".
6. Xem ảnh đã upload trong Media Library hoặc trong kết quả ở meta box.

= API Providers =

Prompt Generation:

* OpenAI: dùng model chat/completions như GPT-5 Nano, GPT-5 Mini hoặc GPT-4o Mini.
* Google Gemini: dùng model Gemini text như Gemini 2.5 Flash Lite.

Image Generation:

* Gemini Enterprise Agent Platform (Vertex AI): Gemini 3.1 Flash Image Preview và Gemini 3 Pro Image Preview.
* 9router/OpenAI-compatible endpoint: endpoint tùy chỉnh hỗ trợ `/v1/models` và `/v1/images/generations`.

= Hỗ Trợ =

* Website: https://airender.vn
* Zalo / Phone: 0896009111 (Phạm Ngọc Tú)

== Installation ==

= Tải file ZIP từ GitHub (đơn giản nhất) =

1. Truy cập https://github.com/led14900/ai-image-generator
2. Nhấn nút "Code" (màu xanh) và chọn "Download ZIP".
3. Giải nén, đổi tên folder thành `ai-image-generator-congcuseoai`.
4. Upload folder vào `/wp-content/plugins/`.
5. Vào WordPress Admin > Plugins và kích hoạt.
6. Vào Media > AI Image Generator để cấu hình.

= Thủ Công =

1. Upload folder `ai-image-generator-congcuseoai` vào `/wp-content/plugins/`.
2. Vào WordPress Admin > Plugins.
3. Kích hoạt "AI Image Generator by CongCuSEOAI".
4. Vào Media > AI Image Generator để cấu hình.

== Frequently Asked Questions ==

= Plugin này miễn phí không? =

Plugin miễn phí. Bạn cần tự cung cấp API key từ các provider bên thứ ba và chi phí phụ thuộc vào provider đó.

= Prompt có phải tiếng Việt không? =

Có. Prompt mặc định được tạo bằng tiếng Việt từ nội dung bài viết. Bạn có thể chỉnh system prompt trong Settings.

= 9router là gì? =

9router là endpoint tùy chỉnh tương thích OpenAI API. Trong plugin này, cấu hình 9router dành cho CLIProxy của 9router, sử dụng tài khoản Codex để tạo ảnh qua model `cx/gpt-5.4-image`. Quản trị viên có thể cấu hình endpoint local hoặc remote có hỗ trợ `/v1/models` và `/v1/images/generations`.

= Cấu hình 9router như thế nào? =

1. Chạy CLIProxy của 9router local hoặc chuẩn bị endpoint remote tương thích OpenAI Images API.
2. Kiểm tra endpoint có phản hồi bằng cách mở hoặc test `{endpoint}/v1/models`.
3. Trong Media > AI Image Generator > Cấu hình Tạo Ảnh, chọn `9router (Codex)`.
4. Nhập Endpoint URL. Khuyến nghị dùng base URL như `http://localhost:20128`; plugin cũng chấp nhận `http://localhost:20128/v1` hoặc full URL `http://localhost:20128/v1/images/generations` và tự chuẩn hóa.
5. Dán API key hoặc chuỗi Bearer token từ curl vào ô 9router API Key.
6. Nhập model, ví dụ `cx/gpt-5.4-image`, rồi cấu hình size, quality, background, image_detail và output format.
7. Bấm Test Kết Nối trước khi tạo ảnh thật.

Nếu gặp lỗi 404, hãy kiểm tra endpoint có route `/v1/images/generations` và tránh nhập URL sai host/port. Nếu gặp lỗi 401, hãy kiểm tra lại API key hoặc Bearer token.

Ví dụ curl dùng để đối chiếu cấu trúc request:

    curl -X POST http://localhost:20128/v1/images/generations \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer YOUR_9ROUTER_TOKEN" \
      -H "Accept: text/event-stream" \
      -d '{"model":"cx/gpt-5.4-image","prompt":"Mô tả ảnh cần tạo","n":1,"size":"auto","quality":"auto","background":"auto","image_detail":"high","output_format":"png"}'

= Plugin có tự set Featured Image không? =

Không trong phiên bản hiện tại. Plugin upload ảnh vào Media Library và lưu danh sách ảnh đã tạo trong post meta.

= Làm sao để lấy API key? =

* OpenAI: https://platform.openai.com/api-keys
* Google Gemini prompt API: https://aistudio.google.com/apikey
* Gemini Enterprise Agent Platform (Vertex AI) image provider: dùng Google Cloud service account JSON có quyền Vertex AI User. Agent Platform là tên gọi mới của Vertex AI được Google tích hợp vào; về mặt kỹ thuật, plugin vẫn gọi REST endpoint của Vertex AI API.

== Screenshots ==

1. Settings page - cấu hình Prompt AI.
2. Settings page - cấu hình Gemini Enterprise Agent Platform (Vertex AI).
3. Settings page - cấu hình 9router/OpenAI-compatible endpoint.
4. Settings page - tối ưu hóa ảnh.
5. Meta box trong Post Editor.
6. Gallery ảnh đã tạo.

== Third Party Services ==

This plugin connects to third-party services only after a site administrator configures the related provider and API key. These services are required to generate prompts, captions, test connections, and generate images.

= OpenAI API =

Used when OpenAI is selected as the prompt provider.

* Service endpoint: `https://api.openai.com/v1/chat/completions`
* Data sent: post title, excerpt, stripped post content, generated prompt text, selected model, temperature, max token setting, and the configured API key in the Authorization header.
* Purpose: generate image prompts and optional image captions.
* Terms: https://openai.com/policies/terms-of-use
* Privacy: https://openai.com/policies/privacy-policy

= Google Gemini / Gemini Enterprise Agent Platform (Vertex AI) =

Used when Gemini is selected as the prompt provider or Gemini Enterprise Agent Platform (Vertex AI) is selected as the image provider. Gemini Enterprise Agent Platform is Google's rebranded and integrated version of Vertex AI; this plugin uses Vertex AI REST endpoints under the hood.

* Service endpoints: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`, `https://oauth2.googleapis.com/token`, and `https://aiplatform.googleapis.com/v1/projects/{project}/locations/{location}/publishers/google/models/{model}:generateContent`
* Data sent: post title, excerpt, stripped post content, prompt text, image generation settings, selected model, Google Cloud project/location metadata, and OAuth bearer tokens created from the configured service account JSON.
* Purpose: generate prompts, captions, and images.
* Terms: https://ai.google.dev/terms
* Privacy: https://policies.google.com/privacy

= 9router / OpenAI-compatible endpoint =

Used when 9router is selected as the image provider. The endpoint is configured by the site administrator and may be local or remote.

* Service endpoints: `{configured-endpoint}/v1/models` and `{configured-endpoint}/v1/images/generations`
* Data sent: prompt text, model, image size, image quality, background setting, image detail setting, output format, and the configured bearer token.
* Purpose: test endpoint availability and generate images.
* Terms and privacy: depend on the endpoint operator configured by the site administrator.

== Privacy Policy ==

When a provider is used, this plugin may send post title, excerpt, stripped post content, user-edited prompt text, model settings, and image generation settings to the configured third-party API. API keys and Agent Platform service account JSON are stored in the WordPress options table and are not printed back into the settings form after saving.

Generated images are saved temporarily in the WordPress uploads directory, optimized, uploaded to the Media Library, and then the temporary file is deleted.

== Changelog ==

= 1.0.0 - 2026-05-12 =
* Xuất bản Plugin lần đầu tiên.

== Upgrade Notice ==

= 1.0.0 =
Xuất bản lần đầu tiên.

== Credits ==

Developed by Phạm Ngọc Tú — airender.vn
