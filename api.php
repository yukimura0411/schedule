<?php
/**
 * カレンダー API エンドポイント
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/holidays.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDb();

    switch ($action) {
        case 'get_month_data':
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

            if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
                throw new InvalidArgumentException('無効な年月が指定されました。');
            }

            // 前月・当月・翌月の日付範囲を取得 (カレンダー表示枠用)
            $firstDayOfMonth = sprintf('%04d-%02d-01', $year, $month);
            $startDate = date('Y-m-d', strtotime($firstDayOfMonth . ' -14 days'));
            $endDate = date('Y-m-d', strtotime($firstDayOfMonth . ' +45 days'));

            // イベント一覧を取得
            $stmt = $pdo->prepare("
                SELECT * FROM events 
                WHERE event_date BETWEEN :start_date AND :end_date
                ORDER BY event_date ASC, is_all_day DESC, start_time ASC, id ASC
            ");
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate,
            ]);
            $events = $stmt->fetchAll();

            // 祝日を取得 (前年・当年・翌年)
            $holidays = array_merge(
                getJapaneseHolidays($year - 1),
                getJapaneseHolidays($year),
                getJapaneseHolidays($year + 1)
            );

            echo json_encode([
                'success' => true,
                'year' => $year,
                'month' => $month,
                'events' => $events,
                'holidays' => $holidays,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'save_event':
            // JSON または POST データの取得
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $id = !empty($input['id']) ? (int)$input['id'] : null;
            $title = trim($input['title'] ?? '');
            $eventDate = trim($input['event_date'] ?? '');
            $description = trim($input['description'] ?? '');
            $isAllDay = !empty($input['is_all_day']) ? 1 : 0;
            $startTime = $isAllDay ? '' : trim($input['start_time'] ?? '');
            $endTime = $isAllDay ? '' : trim($input['end_time'] ?? '');
            $color = trim($input['color'] ?? '#3b82f6');

            if ($title === '') {
                throw new InvalidArgumentException('タイトルを入力してください。');
            }
            if ($eventDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                throw new InvalidArgumentException('正しい日付を指定してください (YYYY-MM-DD)。');
            }

            if ($id) {
                // 更新
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET title = :title, 
                        description = :description, 
                        event_date = :event_date, 
                        start_time = :start_time, 
                        end_time = :end_time, 
                        color = :color, 
                        is_all_day = :is_all_day, 
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':event_date' => $eventDate,
                    ':start_time' => $startTime,
                    ':end_time' => $endTime,
                    ':color' => $color,
                    ':is_all_day' => $isAllDay,
                    ':id' => $id,
                ]);
                $savedId = $id;
            } else {
                // 新規登録
                $stmt = $pdo->prepare("
                    INSERT INTO events (title, description, event_date, start_time, end_time, color, is_all_day)
                    VALUES (:title, :description, :event_date, :start_time, :end_time, :color, :is_all_day)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':event_date' => $eventDate,
                    ':start_time' => $startTime,
                    ':end_time' => $endTime,
                    ':color' => $color,
                    ':is_all_day' => $isAllDay,
                ]);
                $savedId = (int)$pdo->lastInsertId();
            }

            // 保存したイベント情報を返却
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->execute([':id' => $savedId]);
            $savedEvent = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'message' => $id ? '予定を更新しました。' : '予定を登録しました。',
                'event' => $savedEvent,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete_event':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $id = !empty($input['id']) ? (int)$input['id'] : null;

            if (!$id) {
                throw new InvalidArgumentException('削除する予定のIDが指定されていません。');
            }

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
            $stmt->execute([':id' => $id]);

            echo json_encode([
                'success' => true,
                'message' => '予定を削除しました。',
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new BadMethodCallException('無効なアクションです。');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
