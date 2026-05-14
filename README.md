# Daily Report Google Chat

Ứng dụng PHP nhỏ để gửi daily report lên Google Chat, có lưu lịch sử theo file và hỗ trợ submit kèm Google Form chấm công.

## Tính năng

- Gửi report theo từng project qua Google Chat webhook riêng.
- Thêm nhiều task hôm nay, mỗi task có nội dung, tiến độ và ngày dự kiến tùy chọn.
- Thêm task ngày mai.
- Tự đánh giá chất lượng công việc và tinh thần.
- Ghi chú thêm cho report.
- Sidebar thiết lập sticky để cấu hình project, webhook, logo và Google Form.
- Lưu lịch sử report trong `public/history/`.

## Yêu cầu

- Docker
- Docker Compose
- Trình duyệt có internet để tải CDN Tailwind CSS, jQuery và SweetAlert2.

## Cài đặt lần đầu

Clone repo:

```bash
git clone https://github.com/phamle21/daily-report-gg-chat.git
cd daily-report-gg-chat
```

Chạy app:

```bash
docker compose up -d
```

Mở app:

```text
http://localhost:8081
```

## Thiết lập project

Ở sidebar `Thiết lập`, cấu hình các thông tin cơ bản:

- `Project mặc định khi mở form`: project được chọn sẵn khi mở trang.
- `Tên project`: tên hiển thị trong report, ví dụ `JRR`, `Primass`.
- `Webhook Google Chat`: webhook URL của Google Chat space.
- `Logo/avatar URL`: logo hiển thị trên form và trên Google Chat card.

Có thể bấm `Thêm` để thêm project tùy chọn. Mỗi project có webhook và logo riêng.

Sau khi nhập xong, bấm `Lưu thiết lập`.

## Thiết lập Google Form

Nếu muốn submit kèm Google Form, bật `Bật gửi kèm Google Form` và nhập:

- `Google Form response URL`
- `Email chấm công`
- `Bộ phận/nhóm`
- Giờ/phút bắt đầu và kết thúc
- `Ghi chú gửi lên form`

Nếu không dùng Google Form, tắt checkbox này. Report vẫn gửi lên Google Chat bình thường.

## Cách dùng

1. Chọn project cần gửi report.
2. Nhập ít nhất một task hôm nay.
3. Chọn tiến độ task.
4. Ngày dự kiến có thể để trống.
5. Nhập task ngày mai nếu có.
6. Chọn chất lượng công việc và tinh thần.
7. Nhập ghi chú nếu cần.
8. Bấm `Gửi báo cáo`.

Xem lịch sử report tại:

```text
http://localhost:8081/history.php
```

## Cấu trúc source

```text
Dockerfile
docker-compose.yml
nginx/default.conf
public/
  index.php          Form gửi daily report
  history.php        Trang xem lịch sử report
  main.js            Tương tác UI và AJAX
  config.php         Config template an toàn cho repo
  save-config.php    Lưu thiết lập từ sidebar
  send-webhook.php   Gửi Google Chat và Google Form
  history/.gitkeep   Giữ thư mục history trong git
  logs/.gitkeep      Giữ thư mục logs trong git
```

## Config và dữ liệu runtime

`public/config.php` là config template an toàn để commit.

Khi lưu thiết lập trong app, hệ thống sẽ ghi config vào:

- `public/config.php` nếu PHP có quyền ghi file này.
- `public/history/app-config.php` nếu `public/config.php` không ghi được.

Các file runtime sau không commit lên git:

- `public/history/*.log`
- `public/history/app-config.php`
- `public/logs/*`

## Bảo mật

Không commit webhook thật, email nội bộ hoặc Google Form URL thật lên repo public.

Nếu đã từng commit nhầm secret, hãy rotate webhook/token trên Google Chat hoặc Google Form liên quan.

## Dừng app

```bash
docker compose down
```
