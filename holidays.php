<?php
/**
 * 日本の祝日判定ライブラリ
 */

function getJapaneseHolidays(int $year): array {
    $holidays = [];

    // 固定祝日
    $holidays["{$year}-01-01"] = '元日';
    $holidays["{$year}-02-11"] = '建国記念の日';
    if ($year >= 2020) {
        $holidays["{$year}-02-23"] = '天皇誕生日';
    }
    $holidays["{$year}-04-29"] = '昭和の日';
    $holidays["{$year}-05-03"] = '憲法記念日';
    $holidays["{$year}-05-04"] = 'みどりの日';
    $holidays["{$year}-05-05"] = 'こどもの日';
    if ($year >= 2016) {
        $holidays["{$year}-08-11"] = '山の日';
    }
    $holidays["{$year}-11-03"] = '文化の日';
    $holidays["{$year}-11-23"] = '勤労感謝の日';

    // ハッピーマンデー (第N月曜日)
    // 成人の日: 1月第2月曜日
    $holidays[getNthDayOfWeek($year, 1, 1, 2)] = '成人の日';
    // 海の日: 7月第3月曜日
    $holidays[getNthDayOfWeek($year, 7, 1, 3)] = '海の日';
    // 敬老の日: 9月第3月曜日
    $holidays[getNthDayOfWeek($year, 9, 1, 3)] = '敬老の日';
    // スポーツの日: 10月第2月曜日
    $holidays[getNthDayOfWeek($year, 10, 1, 2)] = 'スポーツの日';

    // 春分の日 (簡易計算式 1980〜2099年)
    $springEquinoxDay = (int)floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    $holidays[sprintf('%04d-03-%02d', $year, $springEquinoxDay)] = '春分の日';

    // 秋分の日 (簡易計算式 1980〜2099年)
    $autumnEquinoxDay = (int)floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    $holidays[sprintf('%04d-09-%02d', $year, $autumnEquinoxDay)] = '秋分の日';

    // 国民の休日 (祝日に挟まれた平日)
    // 例: 9月の敬老の日と秋分の日の間
    $sept3rdMonday = getNthDayOfWeek($year, 9, 1, 3);
    $autumnEquinox = sprintf('%04d-09-%02d', $year, $autumnEquinoxDay);
    $diff = (strtotime($autumnEquinox) - strtotime($sept3rdMonday)) / 86400;
    if ($diff == 2) {
        $sandwichedDay = date('Y-m-d', strtotime($sept3rdMonday . ' +1 day'));
        $holidays[$sandwichedDay] = '国民の休日';
    }

    // 振替休日
    // 祝日が日曜日の場合、その日後においてその日に最も近い「国民の祝日でない日」を振替休日とする
    ksort($holidays);
    $substituteHolidays = [];
    foreach ($holidays as $dateStr => $name) {
        $w = (int)date('w', strtotime($dateStr));
        if ($w === 0) { // 日曜日
            $next = date('Y-m-d', strtotime($dateStr . ' +1 day'));
            while (isset($holidays[$next]) || isset($substituteHolidays[$next])) {
                $next = date('Y-m-d', strtotime($next . ' +1 day'));
            }
            $substituteHolidays[$next] = '振替休日';
        }
    }

    $allHolidays = array_merge($holidays, $substituteHolidays);
    ksort($allHolidays);
    return $allHolidays;
}

/**
 * 指定年月の第N X曜日を取得 (w: 0=日, 1=月, ..., 6=土)
 */
function getNthDayOfWeek(int $year, int $month, int $targetW, int $n): string {
    $firstDay = sprintf('%04d-%02d-01', $year, $month);
    $firstW = (int)date('w', strtotime($firstDay));
    $offset = ($targetW - $firstW + 7) % 7;
    $day = 1 + $offset + ($n - 1) * 7;
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}
