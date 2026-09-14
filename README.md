# Daily Report Google Chat

Ứng dụng web PHP để tạo daily report, gửi lên Slack hoặc Google Chat, lưu lịch sử và tùy chọn gửi kèm Google Form chấm công.

## Tính năng

- Quản lý nhiều project, mỗi project có webhook và logo riêng.
- Thêm, xóa, kéo thả sắp xếp task hôm nay và ngày mai.
- Tiến độ, ngày dự kiến, chất lượng công việc và tinh thần.
- Tự lưu/khôi phục bản nháp trên trình duyệt.
- Nhập nhiều task, preview report và phím tắt `Ctrl/Cmd + Enter`.
- Dashboard lịch sử với tìm kiếm và bộ lọc.
- CSRF protection, TLS verification và dữ liệu runtime tách khỏi source.

## Yêu cầu

- Docker Engine hoặc Docker Desktop.
- Docker Compose V2 (lệnh `docker compose`).
- User hiện tại có quyền chạy Docker. Có thể kiểm tra bằng `docker info`.
- Trình duyệt có internet để tải Tailwind CSS, jQuery, SweetAlert2 và SortableJS từ CDN.

Không cần cài PHP, Nginx, Node.js, Composer, không cần đổi UID/GID và không cần `chmod` source.

## Cài đặt và chạy

```bash
git clone https://github.com/phamle21/daily-report-gg-chat.git
cd daily-report-gg-chat
docker compose up -d --build
```

Mở `http://localhost:8081` và kiểm tra container bằng:

```bash
docker compose ps
```

Cả `php` và `nginx` sẽ chuyển sang trạng thái healthy. Đây là toàn bộ phần cài đặt mặc định trên Linux, macOS và Windows có Docker Desktop.

## Thiết lập ứng dụng lần đầu

Mở nút `Thiết lập` trên header. Hướng dẫn lấy từng thông tin cũng có sẵn ngay trong drawer.

### Project

- `Project mặc định`: project được chọn khi mở form.
- `Tên project`: ví dụ `JRR`, `Babyface`.
- `Webhook Google Chat`: URL webhook của Space, bắt đầu bằng `https://chat.googleapis.com/`.
- `Logo/avatar URL`: URL ảnh HTTPS có thể truy cập công khai; ảnh vuông hiển thị tốt nhất.

Bấm `Lưu thiết lập`. Webhook và thông tin thật chỉ được lưu trong Docker volume, không ghi vào source Git.

### Slack

Trong Slack App, bật **Incoming Webhooks**, chọn **Add New Webhook to Workspace** và cấp quyền cho channel cần gửi. Dán URL vào **Webhook Slack** của project rồi lưu thiết lập.

RCNV logtime và Google Chat được chọn mặc định; Slack mặc định bỏ chọn. Nhóm **Submit kèm** cho phép chọn độc lập **RCNV logtime**, **Google Chat**, **Slack**, hoặc nhiều mục trong cùng một lần gửi. Kết quả hiển thị riêng từng mục; khi gửi lại sau lỗi một phần, chỉ các mục thất bại còn được chọn. Lưu người báo cáo trong Thiết lập. Chọn ngày báo cáo (mặc định hôm nay); ngày này dùng cho Slack, Google Chat, RCNV logtime và lịch sử. Mỗi dòng công việc có nội dung, loại, tiến độ và ngày dự kiến. Nếu có Issue, nhập mã ở đầu nội dung, ví dụ `#999 Sửa lỗi thêm sản phẩm`. Nhập kết quả hôm nay ngay trên ghi chú. Trạng thái tự tính từ tiến độ; chất lượng mức 4 và tinh thần mức 4 hiển thị **Tốt**. Báo cáo có bốn mục: công việc, tự đánh giá, tinh thần, chia sẻ thêm. Ngày gửi dùng múi giờ Việt Nam.

### Google Form gửi kèm

Giữ tùy chọn **Submit kèm Google Form** khi gửi báo cáo qua Slack hoặc Google Chat. Trong Thiết lập → Google Form, nhập response URL (`/formResponse`), email, bộ phận, giờ làm và ghi chú. Backend sử dụng mapping `entry.*` trong `send-webhook.php`.

Giữ **Submit Google Form bù** để chấm công theo khoảng ngày.

## Cách sử dụng

1. Chọn project.
2. Nhập ít nhất một task hôm nay.
3. Chọn tiến độ.
4. Task dưới 100% phải có ngày dự kiến từ ngày báo cáo trở đi.
5. Thêm task ngày mai nếu cần.
6. Chọn chất lượng và tinh thần.
7. Preview rồi bấm `Gửi báo cáo`.

Lịch sử nằm tại `http://localhost:8081/history.php`.

## Dữ liệu và tính portable

Source được copy trực tiếp vào image PHP và Nginx khi build. Compose không bind-mount thư mục source từ host, vì vậy ứng dụng không phụ thuộc owner, UID/GID hoặc permission của user đã clone repository.

Docker tự tạo hai named volume:

| Volume | Dữ liệu |
|---|---|
| `history-data` | Config runtime và lịch sử report |
| `logs-data` | Log PHP và gửi báo cáo |

Các volume vẫn tồn tại sau `docker compose down`. Không chạy `docker compose down -v` trừ khi thực sự muốn xóa toàn bộ config, lịch sử và log.

## Cấu hình Docker tùy chọn bằng `.env`

Không cần tạo `.env` trong trường hợp thông thường. Chỉ khi port `8081` đã được ứng dụng khác sử dụng:

```bash
cp .env.example .env
```

Sửa `.env`:

```dotenv
DAILY_REPORT_PORT=8090
```

Sau đó chạy `docker compose up -d`. Ứng dụng sẽ có tại `http://localhost:8090`. File `.env` được Git bỏ qua.

## Cập nhật phiên bản mới

```bash
git pull
docker compose up -d --build
```

Named volumes không bị xóa nên thiết lập và lịch sử được giữ nguyên.

## Dừng hoặc gỡ ứng dụng

Dừng container và giữ dữ liệu:

```bash
docker compose down
```

Chỉ khi muốn xóa cả config, lịch sử và log:

```bash
docker compose down -v
```

Lệnh thứ hai không thể hoàn tác.

## Cấu trúc chính

```text
.env.example             Cấu hình port tùy chọn
Dockerfile               Multi-stage image PHP và Nginx
docker-compose.yml       Dịch vụ và named volumes
nginx/default.conf       Web server config
public/
  index.php              Form daily report
  history.php            Dashboard lịch sử
  main.js                UI và AJAX
  config.php             Config mặc định an toàn
  save-config.php        Lưu config vào volume
  send-webhook.php       Gửi Slack/Google Chat
  slack-report.php       Định dạng báo cáo Slack
```

## Bảo mật

- Không commit webhook thật.
- Không copy `app-config.php` từ volume vào repository.
- Nếu webhook từng bị lộ, hãy xóa/rotate webhook trong Google Chat Space.
