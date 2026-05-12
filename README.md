# AI Image Generator by CongCuSEOAI

WordPress plugin tự động tạo ảnh minh họa cho bài viết bằng AI — hỗ trợ **Google Gemini Enterprise Agent Platform** *(Vertex AI)* và **9router/OpenAI-compatible endpoint** (Codex).

[![License: GPLv2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net/)

---

## ✨ Tính năng

- **Tạo Prompt tự động** từ tiêu đề, tóm tắt và nội dung bài viết (OpenAI hoặc Gemini)
- **Tạo Ảnh** bằng Gemini Enterprise Agent Platform hoặc 9router/OpenAI-compatible endpoint
- **Chỉnh sửa Prompt** trước khi tạo ảnh
- **Tối ưu ảnh**: resize, nén JPEG, tuỳ chọn chuyển WebP
- **Upload vào Media Library** WordPress tự động
- **Test kết nối** API ngay trong Settings

---

## 🚀 Cài đặt

### Cách 1 — Tải file ZIP từ GitHub (đơn giản nhất)

1. Truy cập trang GitHub: **[github.com/led14900/ai-image-generator](https://github.com/led14900/ai-image-generator)**
2. Nhấn nút **Code** (màu xanh) → chọn **Download ZIP**
3. Giải nén → đổi tên folder thành `ai-image-generator-congcuseoai`
4. Upload folder vào `/wp-content/plugins/` của WordPress
5. Kích hoạt trong **WordPress Admin → Plugins**
6. Vào **Media → AI Image Generator** để cấu hình

### Cách 2 — Clone repo

```bash
git clone https://github.com/led14900/ai-image-generator.git ai-image-generator-congcuseoai
```

Sau đó copy folder `ai-image-generator-congcuseoai` vào `/wp-content/plugins/`.

---

## ⚙️ Cấu hình

### Prompt Provider (chọn 1)
| Provider | Model gợi ý |
|----------|-------------|
| OpenAI | `gpt-5-nano-2025-08-07` |
| Google Gemini | `gemini-2.5-flash-lite` |

### Image Provider (chọn 1)
| Provider | Ghi chú |
|----------|---------|
| Gemini Enterprise Agent Platform *(Vertex AI)* | Cần Google Cloud service account JSON |
| 9router / OpenAI-compatible | Nhập base URL endpoint + Bearer token |

---

## 🔒 Bảo mật

- Nonce AJAX cho tất cả requests
- `current_user_can()` trước mọi thao tác
- Sanitize/escape đầy đủ theo WordPress Coding Standards
- API key không được in lại ra HTML sau khi lưu
- WP_Filesystem API thay vì `file_get_contents`/`file_put_contents` trực tiếp

---

## 📡 Third-party Services

Plugin gửi dữ liệu tới các API bên ngoài **chỉ khi người dùng đã cấu hình và kích hoạt provider đó**:

| Service | Mục đích | Terms |
|---------|----------|-------|
| OpenAI API | Tạo prompt | [Terms](https://openai.com/policies/terms-of-use) |
| Google Gemini API | Tạo prompt | [Terms](https://ai.google.dev/terms) |
| Gemini Enterprise Agent Platform *(Vertex AI)* | Tạo ảnh | [Terms](https://ai.google.dev/terms) |
| 9router / custom endpoint | Tạo ảnh | Phụ thuộc nhà vận hành endpoint |

---

## 📄 License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 📞 Liên hệ & Hỗ trợ

Được phát triển bởi **[airender.vn](https://airender.vn)**

| | |
|---|---|
| 📱 **Zalo / Phone** | [0896009111](tel:0896009111) — Phạm Ngọc Tú |
| 🌐 **Website** | [airender.vn](https://airender.vn) |
| 🌐 **CongCuSEOAI** | [congcuseoai.com](https://congcuseoai.com) |
| 📘 **Facebook** | [fb.com/ngoctu.gttn](https://www.facebook.com/ngoctu.gttn) |

