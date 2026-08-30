<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cache_dir = __DIR__ . '/cache';
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

$action = $_GET['action'] ?? 'get_categories';
$fa_param = $_GET['fa'] ?? '';
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// Category master list mapping
$categories_master = [
    ["fa" => "SDQ", "name" => "IT-Software / DB / QA / Web / Graphics"],
    ["fa" => "HNS", "name" => "IT-Hardware / Networks / Systems"],
    ["fa" => "ITT", "name" => "IT-Telecoms"],
    ["fa" => "ACA", "name" => "Accounting / Auditing / Finance"],
    ["fa" => "BAF", "name" => "Banking & Finance / Insurance"],
    ["fa" => "SMM", "name" => "Sales / Marketing / Merchandising"],
    ["fa" => "HAT", "name" => "HR / Training"],
    ["fa" => "COM", "name" => "Corporate Management / Analysts"],
    ["fa" => "OAS", "name" => "Office Admin / Secretary / Receptionist"],
    ["fa" => "CCE", "name" => "Civil Eng / Interior Design / Architecture"],
    ["fa" => "CUR", "name" => "Customer Relations / Public Relations"],
    ["fa" => "LWT", "name" => "Logistics / Warehouse / Transport"],
    ["fa" => "MAE", "name" => "Engineering - Mech / Auto / Elec"],
    ["fa" => "POS", "name" => "Manufacturing / Operations"],
    ["fa" => "MAC", "name" => "Media / Advertising / Communication"],
    ["fa" => "HRF", "name" => "Hotel / Restaurant / Hospitality"],
    ["fa" => "HOT", "name" => "Travel / Tourism"],
    ["fa" => "SRF", "name" => "Sports / Fitness / Recreation"],
    ["fa" => "MHN", "name" => "Medical / Nursing / Healthcare"],
    ["fa" => "LEL", "name" => "Legal / Law"],
    ["fa" => "SQC", "name" => "Supervision / Quality Control"],
    ["fa" => "APC", "name" => "Apparel / Clothing"],
    ["fa" => "AIM", "name" => "Ticketing / Airline / Marine"],
    ["fa" => "TAL", "name" => "Education / Teaching"],
    ["fa" => "RLT", "name" => "R&D / Science / Research"],
    ["fa" => "AGD", "name" => "Agriculture / Dairy / Environment"],
    ["fa" => "SEC", "name" => "Security"],
    ["fa" => "BEC", "name" => "Fashion / Design / Beauty"],
    ["fa" => "IDV", "name" => "International Development"],
    ["fa" => "KPO", "name" => "KPO / BPO"],
    ["fa" => "IME", "name" => "Imports / Exports"]
];

function scrape_category($fa) {
    $url = "https://www.topjobs.lk/applicant/vacancybyfunctionalarea.jsp?FA=" . urlencode($fa);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return [];

    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $tr_matches);
    $jobs = [];

    foreach ($tr_matches[0] as $tr_html) {
        if (strpos($tr_html, 'JobAdvertismentServlet') !== false) {
            // Extract job URL
            $job_url = '';
            if (preg_match('/openSizeWindow\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i', $tr_html, $url_m)) {
                $raw_url = str_replace('../', '', $url_m[1]);
                $job_url = "https://www.topjobs.lk/" . ltrim($raw_url, '/');
            }

            // Extract Job Title
            $title = '';
            if (preg_match('/<h2>\s*<span[^>]*>(.*?)<\/span>\s*<\/h2>/is', $tr_html, $t_m)) {
                $title = trim(strip_tags($t_m[1]));
            } elseif (preg_match('/<h2>(.*?)<\/h2>/is', $tr_html, $t_m)) {
                $title = trim(strip_tags($t_m[1]));
            }

            // Extract Company Name
            $company = '';
            if (preg_match('/<h1>(.*?)<\/h1>/is', $tr_html, $c_m)) {
                $company = trim(strip_tags($c_m[1]));
            }

            if (!empty($title) && !empty($job_url)) {
                $jobs[] = [
                    'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'company' => !empty($company) ? html_entity_decode($company, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Company Name Withheld',
                    'url' => $job_url
                ];
            }
        }
    }

    return $jobs;
}

function get_cached_category($fa, $cache_dir, $force_refresh = false) {
    $cache_file = $cache_dir . "/cat_" . strtolower($fa) . ".json";
    $ttl = 1800; // 30 mins

    if (!$force_refresh && file_exists($cache_file) && (time() - filemtime($cache_file) < $ttl)) {
        $content = file_get_contents($cache_file);
        $data = json_decode($content, true);
        if ($data !== null) {
            $data['cached'] = true;
            $data['cache_age_mins'] = round((time() - filemtime($cache_file)) / 60);
            return $data;
        }
    }

    $jobs = scrape_category($fa);
    $result = [
        'fa' => $fa,
        'count' => count($jobs),
        'jobs' => $jobs,
        'scraped_at' => date('Y-m-d H:i:s'),
        'cached' => false
    ];

    file_put_contents($cache_file, json_encode($result));
    return $result;
}

if ($action === 'get_categories') {
    $list = [];
    foreach ($categories_master as $cat) {
        $fa = $cat['fa'];
        $cache_file = $cache_dir . "/cat_" . strtolower($fa) . ".json";
        $job_count = null;
        $last_scraped = null;

        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            if ($data) {
                $job_count = $data['count'] ?? count($data['jobs'] ?? []);
                $last_scraped = $data['scraped_at'] ?? null;
            }
        }

        $list[] = [
            'fa' => $fa,
            'name' => $cat['name'],
            'url' => "https://www.topjobs.lk/applicant/vacancybyfunctionalarea.jsp?FA=" . $fa,
            'job_count' => $job_count,
            'last_scraped' => $last_scraped
        ];
    }
    echo json_encode([
        'status' => 'success',
        'categories' => $list
    ]);
    exit;
}

if ($action === 'scrape_category') {
    if (empty($fa_param)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing category FA code']);
        exit;
    }

    $result = get_cached_category($fa_param, $cache_dir, $force_refresh);
    
    // Find category name
    $name = $fa_param;
    foreach ($categories_master as $cat) {
        if ($cat['fa'] === $fa_param) {
            $name = $cat['name'];
            break;
        }
    }
    $result['name'] = $name;
    $result['status'] = 'success';

    echo json_encode($result);
    exit;
}

if ($action === 'scrape_all') {
    $all_data = [];
    $total_jobs = 0;
    foreach ($categories_master as $cat) {
        $fa = $cat['fa'];
        $res = get_cached_category($fa, $cache_dir, $force_refresh);
        $res['name'] = $cat['name'];
        $all_data[$fa] = $res;
        $total_jobs += $res['count'];
    }

    echo json_encode([
        'status' => 'success',
        'total_jobs' => $total_jobs,
        'categories' => $all_data
    ]);
    exit;
}

if ($action === 'clear_cache') {
    $files = glob($cache_dir . "/*.json");
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    echo json_encode(['status' => 'success', 'message' => 'Cache cleared successfully']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
