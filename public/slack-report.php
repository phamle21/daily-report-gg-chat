<?php

function formatSlackReport($reporter, $date, $tasks, $result, $quality, $spirit, $note)
{
    // Escape Slack control characters so user text cannot create mentions or links.
    $escape = static fn($value) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$value);
    $code = static fn($value) => '`' . str_replace(["`", "\r", "\n"], ['', ' ', ' '], $escape($value)) . '`';
    $qualityLabels = [1 => 'Kém', 2 => 'Trung bình', 3 => 'Khá', 4 => 'Tốt', 5 => 'Xuất sắc'];
    $spiritLabels = [1 => 'Rất không tốt', 2 => 'Không tốt', 3 => 'Bình thường', 4 => 'Tốt', 5 => 'Rất tốt'];
    $text = "*BÁO CÁO CÔNG VIỆC HẰNG NGÀY*\n\n*Người báo cáo:* " . $code($reporter);
    $text .= "\n*Ngày:* " . $code($date) . "\n\n*1. CÔNG VIỆC TRONG NGÀY*\n";
    // Slack trims plain leading spaces, so nested bullets are indented with em spaces.
    $indent = "\u{2003}\u{2003}◦ ";
    // Task nhiều dòng: dòng sau thụt vào ngang với chữ của bullet con.
    $wrap = static fn($value) => str_replace("\n", "\n\u{2003}\u{2003}\u{2003}", str_replace("\r\n", "\n", (string)$value));
    foreach ($tasks as $task) {
        $progress = $task['progress'];
        $text .= "\n• *Issue:* " . $code($task['issue_no'] ?: 'Không có');
        $text .= "\n" . $indent . "*Công việc:* " . $wrap($escape($task['content']));
        $text .= "\n" . $indent . "*Loại:* " . $escape($task['work_type'] ?: 'Coding');
        $text .= "\n" . $indent . "*Trạng thái:* " . $escape($task['status'] ?: 'Đang thực hiện');
        $text .= "\n" . $indent . "*Tiến độ:* " . ($progress === '' ? 'Chưa cập nhật' : $progress . '%');
        if (!empty($task['estimate'])) {
            $text .= "\n" . $indent . "*Ngày dự kiến:* " . $escape($task['estimate']);
        }
        $text .= "\n";
    }
    $text .= "\n*2. TỰ ĐÁNH GIÁ KẾT QUẢ & CHẤT LƯỢNG*\n\n*Kết quả hôm nay:* " . $escape($result ?: 'Không có.');
    $text .= "\n\n*Đánh giá:* " . ($qualityLabels[$quality] ?? 'Khá');
    $text .= "\n\n*3. CẢM XÚC & TINH THẦN*\n\n*Mức độ:* " . ($spiritLabels[$spirit] ?? 'Bình thường');
    $text .= "\n\n*4. CHIA SẺ THÊM*\n\n" . $escape($note ?: 'Không có.');
    return $text;
}
