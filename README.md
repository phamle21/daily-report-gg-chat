# Daily Report Google Chat

Ứng dụng PHP nhỏ để gửi daily report lên Google Chat, có lưu lịch sử theo file và hỗ trợ submit kèm Google Form chấm công.

## Tính năng

- Gửi report theo từng project qua Google Chat webhook riêng.
- Thêm nhiều task hôm nay, mỗi task có nội dung, tiến độ và ngày dự kiến tùy chọn.
- Thêm task ngày mai.
- Tự đánh giá chất lượng công việc và tinh thần.
- Ghi chú thêm cho report.
- Drawer thiết lập tách khỏi khu vực viết report, tối ưu không gian trên desktop và mobile.
- Tự động lưu và khôi phục bản nháp report trên trình duyệt.
- Xem trước nội dung trước khi gửi và hỗ trợ `Ctrl/Cmd + Enter` để gửi nhanh.
- Submit riêng Google Form chấm công cho từng ngày trong một khoảng, không gửi report Google Chat.
- Lưu lịch sử report trong `public/history/`.
- Bảo vệ các thao tác POST bằng CSRF token và xác minh TLS khi gọi dịch vụ ngoài.

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
docker compose up -d --build
```

Mở app:

```text
http://localhost:8081
```

Docker tự tạo hai named volume để lưu dữ liệu runtime:

- `history-data`: cấu hình đã lưu và lịch sử report.
- `logs-data`: log PHP và log gửi Google Chat/Google Form.

Vì vậy ứng dụng không phụ thuộc UID/GID của user trên máy host và có thể chạy cùng cách trên Linux,
macOS hoặc Windows có Docker Desktop. Không cần chạy `chmod 777` cho source.

## Thiết lập project

Mở drawer `Thiết lập` từ nút trên header để cấu hình các thông tin cơ bản:

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

### Submit Google Form bù theo khoảng ngày

Trong drawer `Thiết lập`, tại mục `Submit Google Form bù`:

1. Chọn `Từ ngày` và `Đến ngày`.
2. Bấm `Submit các ngày` và xác nhận.
3. Ứng dụng submit lần lượt tất cả ngày trong khoảng, bao gồm cả ngày bắt đầu và ngày kết thúc.

Luồng này chỉ gửi Google Form chấm công, không gửi Google Chat và không tạo lịch sử report. Mỗi lần hỗ trợ tối đa 31 ngày; cần bật và cấu hình Google Form trước khi sử dụng.

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
  submit-google-form.php  Chỉ gửi Google Form cho một ngày được chọn
  history/.gitkeep   Giữ thư mục history trong git
  logs/.gitkeep      Giữ thư mục logs trong git
```

## Config và dữ liệu runtime

`public/config.php` là config mặc định an toàn để commit. Ứng dụng không ghi thông tin thật vào file
này.

Khi bấm `Lưu thiết lập`, hệ thống ghi config runtime vào:

- `/var/www/html/history/app-config.php` trong container.
- File này được giữ trong named volume `history-data` và được ưu tiên khi ứng dụng đọc config.

Lịch sử và log cũng nằm trong named volumes, không được commit lên Git và vẫn tồn tại sau khi chạy
`docker compose down`.

### Chạy trên máy khác

Trên mỗi máy mới:

```bash
git clone https://github.com/phamle21/daily-report-gg-chat.git
cd daily-report-gg-chat
docker compose up -d --build
```

Sau đó mở `http://localhost:8081`, nhập thiết lập và bấm `Lưu thiết lập`. Mỗi máy có named volume và
config riêng; secret không đi theo Git repository.

Nếu muốn chuyển cấu hình/dữ liệu sang máy khác, hãy backup và restore hai named volume thay vì copy
`public/config.php`.

### Kiểm tra lỗi quyền ghi

Kiểm tra container và quyền của thư mục runtime:

```bash
docker compose ps
docker compose exec php id www-data
docker compose exec php ls -ld /var/www/html/history /var/www/html/logs
docker compose exec -u www-data php sh -lc 'test -w /var/www/html/history && test -w /var/www/html/logs'
```

Nếu project đã từng chạy bằng Compose phiên bản cũ, rebuild và tạo lại container để áp dụng mount:

```bash
docker compose down
docker compose up -d --build
```

Không dùng `docker compose down -v` để sửa quyền: tùy chọn `-v` xóa toàn bộ cấu hình, lịch sử và log
đang lưu trong volumes.

Ứng dụng truy cập dữ liệu theo các đường dẫn sau:

| Trong source | Trong container | Nơi lưu thực tế |
|---|---|---|
| `public/history/` | `/var/www/html/history/` | Named volume `history-data` |
| `public/logs/` | `/var/www/html/logs/` | Named volume `logs-data` |

## Bảo mật

Không commit webhook thật, email nội bộ hoặc Google Form URL thật lên repo public.

Nếu đã từng commit nhầm secret, hãy rotate webhook/token trên Google Chat hoặc Google Form liên quan.

## Dừng app

```bash
docker compose down
```
