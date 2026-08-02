<?php
require_once __DIR__ . '/config.php';

/**
 * 发起 HTTP GET 请求，返回原始响应字符串
 */
function http_get($url, $headers = []) {
    $ch = curl_init($url);
    $default = [
        'User-Agent: ' . UA,
        'Accept: application/json, text/plain, */*',
        'Accept-Language: zh-CN,zh;q=0.9',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER      => array_merge($default, $headers),
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_ENCODING        => '',                 // 自动处理 gzip/deflate，避免拿到压缩字节导致解析失败
        CURLOPT_IPRESOLVE       => CURL_IPRESOLVE_V4,  // 优先 IPv4，规避部分服务器 IPv6 解析失败
    ]);
    $resp = curl_exec($ch);
    $GLOBALS['http_last_error'] = curl_error($ch);
    $GLOBALS['http_last_code']  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $resp === false ? '' : $resp;
}

/**
 * 将形如 "428 万热度" / "1.2 万" / "12345" 的热度文本解析为整型数字
 */
function parseHotText($s) {
    if (!is_string($s)) return 0;
    $s = trim($s);
    if (preg_match('/([\d.]+)\s*万/', $s, $m)) {
        return (int)(floatval($m[1]) * 10000);
    }
    $n = preg_replace('/[^\d]/', '', $s);
    return $n === '' ? 0 : (int)$n;
}

/**
 * 通用热榜抓取：通过 uapis.cn 聚合接口获取任意平台热榜
 * 平台 pkey 即接口 type（如 thepaper、xiaohongshu 等），响应格式与 IT之家一致
 */
function fetch_uapis($type) {
    $raw = http_get('https://uapis.cn/api/v1/misc/hotboard?type=' . urlencode($type));
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['list']) && is_array($data['list'])) {
        foreach ($data['list'] as $r) {
            $title = $r['title'] ?? '';
            if ($title === '') continue;
            $items[] = [
                'rank'  => (int)($r['index'] ?? count($items) + 1),
                'title' => $title,
                'url'   => $r['url'] ?? '',
                'hot'   => parseHotText($r['hot_value'] ?? ''),
            ];
        }
    }
    return $items;
}

/**
 * 微博热搜
 */
function fetch_weibo() {
    $raw = http_get('https://weibo.com/ajax/side/hotSearch', ['Referer: https://weibo.com/']);
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['data']['realtime'])) {
        $i = 1;
        foreach ($data['data']['realtime'] as $r) {
            if (!empty($r['is_ad'])) continue;
            $word = $r['word'] ?? '';
            if ($word === '') continue;
            $items[] = [
                'rank'  => $i,
                'title' => $word,
                'url'   => 'https://s.weibo.com/weibo?q=' . urlencode($word),
                'hot'   => isset($r['num']) ? (int)$r['num'] : 0,
            ];
            $i++;
        }
    }
    return $items;
}

/**
 * 百度热搜
 */
function fetch_baidu() {
    $raw = http_get('https://top.baidu.com/api/board?platform=wise&tab=realtime');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['data']['cards']) && is_array($data['data']['cards'])) {
        $i = 1;
        foreach ($data['data']['cards'] as $card) {
            if (!isset($card['content']) || !is_array($card['content'])) continue;
            foreach ($card['content'] as $sub) {
                if (!isset($sub['content']) || !is_array($sub['content'])) continue;
                foreach ($sub['content'] as $r) {
                    $word = $r['word'] ?? '';
                    if ($word === '') continue;
                    $url = $r['rawUrl'] ?? ('https://www.baidu.com/s?wd=' . urlencode($word));
                    $items[] = [
                        'rank'  => $i,
                        'title' => $word,
                        'url'   => $url,
                        'hot'   => 0,
                    ];
                    $i++;
                }
            }
        }
    }
    return $items;
}

/**
 * 知乎热榜（官方接口需登录态，服务器直连返回 401；改用稳定可用的聚合接口）
 */
function fetch_zhihu() {
    $raw = http_get('https://uapis.cn/api/v1/misc/hotboard?type=zhihu');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['list']) && is_array($data['list'])) {
        foreach ($data['list'] as $r) {
            $title = $r['title'] ?? '';
            if ($title === '') continue;
            $items[] = [
                'rank'  => (int)($r['index'] ?? count($items) + 1),
                'title' => $title,
                'url'   => $r['url'] ?? '',
                'hot'   => parseHotText($r['hot_value'] ?? ''),
            ];
        }
    }
    return $items;
}

/**
 * 哔哩哔哩热门
 */
function fetch_bilibili() {
    $raw = http_get('https://api.bilibili.com/x/web-interface/popular?ps=20&pn=1',
        ['Referer: https://www.bilibili.com']);
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['data']['list'])) {
        $i = 1;
        foreach ($data['data']['list'] as $r) {
            $bvid = $r['bvid'] ?? '';
            $items[] = [
                'rank'  => $i,
                'title' => $r['title'] ?? '',
                'url'   => 'https://www.bilibili.com/video/' . $bvid,
                'hot'   => (int)($r['stat']['view'] ?? 0),
            ];
            $i++;
        }
    }
    return $items;
}

/**
 * 今日头条热榜
 */
function fetch_toutiao() {
    $raw = http_get('https://www.toutiao.com/hot-event/hot-board/?origin=toutiao_pc');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['data'])) {
        $i = 1;
        foreach ($data['data'] as $r) {
            $title = $r['Title'] ?? '';
            if ($title === '') continue;
            $u = $r['Url'] ?? '';
            if (strpos($u, 'http') !== 0) {
                $u = 'https://www.toutiao.com' . $u;
            }
            $items[] = [
                'rank'  => $i,
                'title' => $title,
                'url'   => $u,
                'hot'   => (int)($r['HotValue'] ?? 0),
            ];
            $i++;
        }
    }
    return $items;
}

/**
 * 抖音热点
 */
function fetch_douyin() {
    $raw = http_get('https://www.iesdouyin.com/web/api/v2/hotsearch/billboard/word/');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['word_list']) && is_array($data['word_list'])) {
        $i = 1;
        foreach ($data['word_list'] as $r) {
            $word = $r['word'] ?? '';
            if ($word === '') continue;
            $items[] = [
                'rank'  => $i,
                'title' => $word,
                'url'   => 'https://www.douyin.com/search/' . urlencode($word),
                'hot'   => (int)($r['hot_value'] ?? 0),
            ];
            $i++;
        }
    }
    return $items;
}

/**
 * 36氪热榜
 */
function fetch_kr36() {
    $raw = http_get('https://gateway.36kr.com/api/misite/rank/list?category=hot');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['data']['list'])) {
        $i = 1;
        foreach ($data['data']['list'] as $r) {
            $item = $r['itemInfo'] ?? [];
            $title = $item['itemTitle'] ?? '';
            if ($title === '') continue;
            $id = $item['itemId'] ?? '';
            $items[] = [
                'rank'  => $i,
                'title' => $title,
                'url'   => 'https://36kr.com/p/' . $id,
                'hot'   => (int)($item['heatNum'] ?? 0),
            ];
            $i++;
        }
    }
    return $items;
}

/**
 * GitHub 趋势（官网 trending 页面在部分服务器网络不可达，改用 api.github.com 搜索接口：
 * 取“近一日新建、按 star 排序”的仓库作为当日热门代理，接口稳定且无需登录）
 */
function fetch_github() {
    $since = date('Y-m-d', strtotime('-1 day'));
    $raw = http_get(
        'https://api.github.com/search/repositories?q=created:%3E' . urlencode($since) . '&sort=stars&order=desc&per_page=20',
        ['User-Agent: Mozilla/5.0', 'Accept: application/vnd.github+json']
    );
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['items']) && is_array($data['items'])) {
        $rank = 1;
        foreach ($data['items'] as $r) {
            $name = $r['full_name'] ?? '';
            if ($name === '') continue;
            $title = $name;
            if (!empty($r['description'])) {
                $title .= ' — ' . $r['description'];
            }
            $items[] = [
                'rank'  => $rank,
                'title' => $title,
                'url'   => $r['html_url'] ?? '',
                'hot'   => (int)($r['stargazers_count'] ?? 0),
            ];
            $rank++;
        }
    }
    return $items;
}

/**
 * IT之家热榜（使用免费聚合 API，已验证可用；热度值为空时不显示）
 */
function fetch_ithome() {
    $raw = http_get('https://uapis.cn/api/v1/misc/hotboard?type=ithome');
    $data = json_decode($raw, true);
    $items = [];
    if (!empty($data['list'])) {
        foreach ($data['list'] as $r) {
            $title = $r['title'] ?? '';
            if ($title === '') continue;
            $items[] = [
                'rank'  => (int)($r['index'] ?? count($items) + 1),
                'title' => $title,
                'url'   => $r['url'] ?? '',
                'hot'   => 0,
            ];
        }
    }
    return $items;
}
