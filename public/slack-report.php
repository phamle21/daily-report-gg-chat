<?php

function formatSlackReport($reporter, $date, $tasks, $result, $quality, $spirit, $note)
{
    // Escape Slack control characters so user text cannot create mentions or links.
    $escape = static fn($value) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$value);
    $code = static fn($value) => '`' . str_replace(["`", "\r", "\n"], ['', ' ', ' '], $escape($value)) . '`';
    $qualityLabels = [1 => 'Kém', 2 => 'Trung bình', 3 => 'Khá', 4 => 'Tốt', 5 => 'Xuất sắc'];
    $spiritLabels = [1 => 'Cạn pin', 2 => 'Cần cà phê', 3 => 'Ổn định', 4 => 'Tốt', 5 => 'Bứt phá'];
    $text = "*BÁO CÁO CÔNG VIỆC HẰNG NGÀY*\n\n*Người báo cáo:* " . $code($reporter);
    $text .= "\n*Ngày:* " . $code($date) . "\n\n*1. CÔNG VIỆC TRONG NGÀY*\n";
    foreach ($tasks as $task) {
        $progress = $task['progress'];
        $status = $progress === '' ? 'Chưa cập nhật' : ($progress === 100 ? 'Hoàn thành' : ($progress === 0 ? 'Chưa bắt đầu' : 'Đang thực hiện'));
        $text .= "\n• *Issue:* " . $code($task['issue_no'] ?: 'Không có');
        $text .= "\n    • *Công việc:* " . $escape($task['content']);
        $text .= "\n    • *Loại:* " . $escape($task['work_type'] ?: 'Coding');
        $text .= "\n    • *Trạng thái:* " . $status;
        $text .= "\n    • *Tiến độ:* " . ($progress === '' ? 'Chưa cập nhật' : $progress . '%') . "\n";
    }
    $text .= "\n*2. TỰ ĐÁNH GIÁ KẾT QUẢ & CHẤT LƯỢNG*\n\n*Kết quả hôm nay:* " . $escape($result ?: 'Không có.');
    $text .= "\n\n*Đánh giá:* " . ($qualityLabels[$quality] ?? 'Khá');
    $text .= "\n\n*3. CẢM XÚC & TINH THẦN*\n\n*Mức độ:* " . ($spiritLabels[$spirit] ?? 'Ổn định');
    $text .= "\n\n*4. CHIA SẺ THÊM*\n\n" . $escape($note ?: 'Không có.');
    return $text;
}
