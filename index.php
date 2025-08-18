<?php
/* =============================
   File: index.php
   PHP 7.0.33 compatible UI for media management
   - Renders gallery and upload UI
   - Talks to api.php?action=upload/save via fetch()
   - Loads initial items from `data.json` if present, or from ?json=...
   ============================= */

// --- Config (duplicated in api.php; keep in sync) ---
$BASE_MEDIA_URL = 'https://storage.san-sanych.ua/media/';
$PERSIST_TO_FILE = true; // index.php reads data.json when present

// Resolve initial data: ?json=... > data.json > sample
$incomingJsonStr = null;
if (isset($_REQUEST['json'])) {
    $incomingJsonStr = trim($_REQUEST['json']);
}
if (!$incomingJsonStr && is_file(__DIR__ . '/data.json')) {
    $incomingJsonStr = @file_get_contents(__DIR__ . '/data.json');
}
if (!$incomingJsonStr) {
    $incomingJsonStr = '[
  {"type":"picture","publish":true, "url":"https://messenger.smartsender.com/storage/projects/125024/gates/414754006/IQRUrMyaacvAu7Ia2L2WYdpMJfZwTup8Jv8NFuz5.jpg"},
  {"type":"picture","publish":false,"url":"https://messenger.smartsender.com/storage/projects/125024/gates/414754006/CtCWpG54jjKsHhKndRp85nzTJJ5GIvVjKbgHeyZy.jpg"}
]';
}
$items = json_decode($incomingJsonStr, true);
if (!is_array($items)) {
    $items = array();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Media Manager — Two-file Version</title>
    <style>
        :root {
            --green: #1fa971;
            --red: #e53935;
            --gray: #e0e0e0;
            --bg: #f8f9fb;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 0;
            background: var(--bg);
            color: #333;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .btn.green {
            background: var(--green);
            color: #fff;
            border-color: var(--green);
        }

        .btn.red {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
        }

        .btn.outline {
            background: #fff;
            color: #333;
        }

        .wrap {
            max-width: 1100px;
            margin: 16px auto 40px;
            padding: 0 16px;
        }

        .panel {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }

        .card {
            background: #fff;
            border: 2px solid var(--gray);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: box-shadow .2s, border-color .2s;
        }

        .card.published {
            border-color: var(--green);
            box-shadow: 0 0 0 2px rgba(31, 169, 113, .15) inset;
        }

        .media {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .media img,
        .media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0, 0, 0, .65);
            color: #fff;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 8px;
        }

        .body {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .url {
            font-size: 12px;
            color: #555;
            word-break: break-all;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .checkbox input {
            transform: scale(1.2);
        }

        .upload {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .upload input[type=file] {
            padding: 8px;
            border: 1px dashed #bbb;
            border-radius: 10px;
            background: #fafafa;
        }

        .status {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            white-space: pre-wrap;
            background: #111;
            color: #e0ffe0;
            padding: 10px;
            border-radius: 8px;
            max-height: 240px;
            overflow: auto;
        }

        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .lightbox.open {
            display: flex;
        }

        .lightbox img {
            max-width: 92vw;
            max-height: 92vh;
            object-fit: contain;
        }

        .lightbox .close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: #000;
            color: #fff;
            border: 1px solid #444;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
        }

        .pill {
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 12px;
            border: 1px solid #ddd;
            background: #fff;
        }

        .hint {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <header>
        <h1>Media Manager</h1>
        <div class="toolbar">
            <button class="btn green" id="btnAll">ALL</button>
            <button class="btn red" id="btnNone">NONE</button>
            <button class="btn outline" id="btnAuto">Auto-filter</button>
            <button class="btn" id="btnSave">Save</button>
        </div>
    </header>

    <div class="wrap">
        <div class="panel">
            <div class="upload">
                <input type="file" id="fileInput" accept="image/*,video/*" multiple>
                <label class="checkbox"><input type="checkbox" id="defaultPublish" checked> Publish uploads by
                    default</label>
                <span class="pill">Max 200 MB per file</span>
                <span class="hint">Links are built as
                    <code><?php echo htmlspecialchars($BASE_MEDIA_URL); ?>{filename}</code>.</span>
            </div>
            <div id="uploadStatus" class="status" style="display:none; margin-top:10px;"></div>
        </div>

        <div class="panel">
            <div class="row" style="margin-bottom:6px;">
                <strong>Items</strong>
                <span class="hint">Click an image to enlarge; videos have fullscreen controls.</span>
            </div>
            <div id="grid" class="grid"></div>
        </div>

        <div class="panel">
            <div class="row" style="margin-bottom:6px;">
                <strong>Incoming JSON</strong>
            </div>
            <pre id="incomingJson" class="status"
                style="background:#111;color:#cfe;"><?php echo htmlspecialchars(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)); ?></pre>
        </div>

        <div class="panel">
            <div class="row" style="margin-bottom:6px;">
                <strong>Save Output</strong>
            </div>
            <pre id="saveOutput" class="status" style="display:none;"></pre>
        </div>
    </div>

    <div class="lightbox" id="lightbox">
        <button class="close" id="lbClose">Close</button>
        <img id="lbImg" src="" alt="preview" />
    </div>

    <script>
        var items = <?php echo json_encode($items, JSON_UNESCAPED_SLASHES); ?>;
        items = (items || []).map(function (it) { return { type: (it && it.type === 'video') ? 'video' : 'picture', publish: !!(it && it.publish !== false), url: (it && it.url) || '' }; });

        var gridEl = document.getElementById('grid');
        var uploadStatusEl = document.getElementById('uploadStatus');
        var saveOutputEl = document.getElementById('saveOutput');

        function truncateUrl(u) { if (!u) return ''; return u.length <= 80 ? u : (u.slice(0, 40) + '…' + u.slice(-30)); }

        function render() {
            gridEl.innerHTML = '';
            items.forEach(function (it) {
                var card = document.createElement('div'); card.className = 'card' + (it.publish ? ' published' : '');
                var media = document.createElement('div'); media.className = 'media';
                if (it.type === 'video') {
                    var v = document.createElement('video'); v.src = it.url; v.controls = true; v.playsInline = true; v.preload = 'metadata'; media.appendChild(v);
                    var badge = document.createElement('div'); badge.className = 'badge'; badge.textContent = 'VIDEO'; media.appendChild(badge);
                } else {
                    var img = document.createElement('img'); img.loading = 'lazy'; img.src = it.url; img.alt = 'image'; img.addEventListener('click', function () { openLightbox(it.url); }); media.appendChild(img);
                }
                var body = document.createElement('div'); body.className = 'body';
                var row1 = document.createElement('div'); row1.className = 'row';
                var urlSpan = document.createElement('div'); urlSpan.className = 'url'; urlSpan.textContent = truncateUrl(it.url); row1.appendChild(urlSpan);
                var openBtn = document.createElement('a'); openBtn.href = it.url; openBtn.target = '_blank'; openBtn.className = 'btn outline'; openBtn.textContent = 'Open'; row1.appendChild(openBtn);
                var row2 = document.createElement('label'); row2.className = 'checkbox'; var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = !!it.publish; cb.addEventListener('change', function () { it.publish = cb.checked; render(); }); var span = document.createElement('span'); span.textContent = 'Publish'; row2.appendChild(cb); row2.appendChild(span);
                body.appendChild(row1); body.appendChild(row2);
                card.appendChild(media); card.appendChild(body); gridEl.appendChild(card);
            });
        }

        var lb = document.getElementById('lightbox'); var lbImg = document.getElementById('lbImg'); var lbClose = document.getElementById('lbClose');
        function openLightbox(url) { lbImg.src = url; lb.classList.add('open'); }
        lbClose.addEventListener('click', function () { lb.classList.remove('open'); lbImg.src = ''; });
        lb.addEventListener('click', function (e) { if (e.target === lb) { lb.classList.remove('open'); lbImg.src = ''; } });

        document.getElementById('btnAll').addEventListener('click', function () { items.forEach(function (it) { it.publish = true; }); render(); });
        document.getElementById('btnNone').addEventListener('click', function () { items.forEach(function (it) { it.publish = false; }); render(); });

        var fileInput = document.getElementById('fileInput'); var defaultPublish = document.getElementById('defaultPublish');
        fileInput.addEventListener('change', function () {
            if (!fileInput.files || !fileInput.files.length) return; uploadStatusEl.style.display = 'block'; uploadStatusEl.textContent = 'Uploading ' + fileInput.files.length + ' file(s)...'; var fd = new FormData(); for (var i = 0; i < fileInput.files.length; i++) { fd.append('media[]', fileInput.files[i]); }
            fetch('api.php?action=upload', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (json) { if (!json || !json.results) { uploadStatusEl.textContent = 'Unexpected response'; return; } var added = 0; json.results.forEach(function (res) { if (res.ok) { items.push({ type: (res.type === 'video') ? 'video' : 'picture', publish: !!defaultPublish.checked, url: res.url }); added++; } else { uploadStatusEl.textContent += "\nError: " + (res.name || '') + ' — ' + (res.error || ''); } }); uploadStatusEl.textContent = 'Uploaded ' + added + ' file(s).'; render(); fileInput.value = ''; })
                .catch(function (err) { uploadStatusEl.textContent = 'Upload failed: ' + err; });
        });

        document.getElementById('btnSave').addEventListener('click', function () {
            var payload = { items: items.map(function (it) { return { type: it.type, publish: !!it.publish, url: it.url }; }) };
            fetch('api.php?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                .then(function (r) { return r.json(); })
                .then(function (json) { if (json && json.ok) { saveOutputEl.style.display = 'block'; saveOutputEl.textContent = json.saved || JSON.stringify(payload.items, null, 2); setTimeout(function () { window.location.reload(); }, 800); } else { saveOutputEl.style.display = 'block'; saveOutputEl.textContent = 'Save failed: ' + (json && json.error ? json.error : 'Unknown error'); } })
                .catch(function (err) { saveOutputEl.style.display = 'block'; saveOutputEl.textContent = 'Save failed: ' + err; });
        });

        document.getElementById('btnAuto').addEventListener('click', function () {
            var seen = {}; items.forEach(function (it) { var key = it.url; if (!key) return; if (seen[key]) { it.publish = false; } else { seen[key] = true; } });
            var imageItems = items.map(function (it, idx) { return { it: it, idx: idx }; }).filter(function (o) { return o.it.type === 'picture' && o.it.url; });
            (function loop(i) { if (i >= imageItems.length) { render(); return; } var obj = imageItems[i]; computeMetrics(obj.it.url).then(function (m) { obj.it._hash = m.hash; obj.it._blur = m.blur; if (typeof m.blur === 'number' && m.blur < 20) { obj.it.publish = false; } for (var j = 0; j < i; j++) { var prev = imageItems[j].it; if (!prev._hash) continue; var d = hamming(prev._hash, m.hash); if (d <= 5) { obj.it.publish = false; break; } } }).catch(function () { }).then(function () { loop(i + 1); }); })(0);
        });

        function computeMetrics(url) {
            return new Promise(function (resolve, reject) {
                var img = new Image(); img.crossOrigin = 'anonymous'; img.onload = function () {
                    try {
                        var w = 64, h = 64; var c = document.createElement('canvas'); c.width = w; c.height = h; var ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0, w, h); var data = ctx.getImageData(0, 0, w, h).data; var gray = new Float32Array(w * h); for (var i = 0, p = 0; i < data.length; i += 4, p++) { gray[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]; }
                        var lap = new Float32Array(w * h); var kernel = [0, 1, 0, 1, -4, 1, 0, 1, 0]; for (var y = 1; y < h - 1; y++) { for (var x = 1; x < w - 1; x++) { var idx = y * w + x; var acc = 0; var k = 0; for (var ky = -1; ky <= 1; ky++) { for (var kx = -1; kx <= 1; kx++) { acc += gray[(y + ky) * w + (x + kx)] * kernel[k++]; } } lap[idx] = acc; } }
                        var mean = 0, count = 0; for (var y = 1; y < h - 1; y++) { for (var x = 1; x < w - 1; x++) { mean += lap[y * w + x]; count++; } } mean /= count || 1; var variance = 0; for (var y = 1; y < h - 1; y++) { for (var x = 1; x < w - 1; x++) { var v = lap[y * w + x] - mean; variance += v * v; } } variance /= count || 1;
                        var hw = 8, hh = 8; var c2 = document.createElement('canvas'); c2.width = hw; c2.height = hh; var ctx2 = c2.getContext('2d'); ctx2.drawImage(img, 0, 0, hw, hh); var d2 = ctx2.getImageData(0, 0, hw, hh).data; var g2 = new Float32Array(hw * hh); var sum = 0; for (var i2 = 0, p2 = 0; i2 < d2.length; i2 += 4, p2++) { var val = 0.299 * d2[i2] + 0.587 * d2[i2 + 1] + 0.114 * d2[i2 + 2]; g2[p2] = val; sum += val; } var avg = sum / (hw * hh); var bits = []; for (var p2 = 0; p2 < g2.length; p2++) { bits.push(g2[p2] > avg ? 1 : 0); } var hash = bits.join(''); resolve({ blur: Math.sqrt(variance), hash: hash });
                    } catch (e) { reject(e); }
                };
                img.onerror = function () { reject(new Error('image load failed')); }; img.src = url;
            });
        }
        function hamming(a, b) { if (!a || !b || a.length !== b.length) return 64; var d = 0; for (var i = 0; i < a.length; i++) { if (a[i] !== b[i]) d++; } return d; }

        render();
    </script>
</body>

</html>

<?php
/* =============================
   File: api.php
   PHP 7.0.33 compatible backend endpoints for upload/save
   Endpoints:
     - POST api.php?action=upload  (multipart form, field: media[])
     - POST api.php?action=save    (JSON body { items: [...] })
   ============================= */
?>
<?php
// --- Config (keep in sync with index.php) ---
$BASE_MEDIA_URL = 'https://storage.san-sanych.ua/media/';
$UPLOAD_DIR = __DIR__ . '/uploads';
$PERSIST_TO_FILE = true;
$MAX_BYTES = 200 * 1024 * 1024; // 200 MB

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}

function json_response($arr, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}
function random_base62($len)
{
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $idx = ord(random_bytes(1)) % strlen($alphabet);
        $out .= $alphabet[$idx];
    }
    return $out;
}
function ext_for_mime($mime, $fallbackExt)
{
    $map = array(
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/x-msvideo' => 'avi',
        'video/x-matroska' => 'mkv'
    );
    if (isset($map[$mime]))
        return $map[$mime];
    return $fallbackExt ? strtolower($fallbackExt) : 'bin';
}
function classify_type($mime)
{
    if (strpos($mime, 'image/') === 0)
        return 'picture';
    if (strpos($mime, 'video/') === 0)
        return 'video';
    return 'unknown';
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['media'])) {
        json_response(array('ok' => false, 'error' => 'No file field "media"'));
    }
    $files = $_FILES['media'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    $results = array();

    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];

        $res = array('ok' => false, 'name' => $name);
        if ($error !== UPLOAD_ERR_OK) {
            $res['error'] = 'Upload error code: ' . $error;
            $results[] = $res;
            continue;
        }
        if ($size > $MAX_BYTES) {
            $res['error'] = 'File too large. Max 200 MB';
            $results[] = $res;
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmp_name) : $type;
        if ($finfo)
            finfo_close($finfo);

        $detectedType = classify_type($mime);
        if ($detectedType === 'unknown') {
            $res['error'] = 'Unsupported MIME type: ' . $mime;
            $results[] = $res;
            continue;
        }

        $origExt = '';
        $dotPos = strrpos($name, '.');
        if ($dotPos !== false) {
            $origExt = substr($name, $dotPos + 1);
        }
        $ext = ext_for_mime($mime, $origExt);

        $randLen = 30 + (ord(random_bytes(1)) % 11); // 30..40
        $basename = random_base62($randLen) . '.' . $ext;
        $destPath = $UPLOAD_DIR . '/' . $basename;
        if (!move_uploaded_file($tmp_name, $destPath)) {
            $res['error'] = 'Failed to move uploaded file';
            $results[] = $res;
            continue;
        }

        $url = rtrim($BASE_MEDIA_URL, '/') . '/' . $basename;
        $res['ok'] = true;
        $res['url'] = $url;
        $res['type'] = $detectedType;
        $res['mime'] = $mime;
        $res['filename'] = $basename;
        $results[] = $res;
    }

    json_response(array('results' => $results));
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data || !isset($data['items']) || !is_array($data['items'])) {
        json_response(array('ok' => false, 'error' => 'Invalid JSON payload'), 400);
    }
    $pretty = json_encode($data['items'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($PERSIST_TO_FILE) {
        @file_put_contents(__DIR__ . '/data.json', $pretty);
    }
    json_response(array('ok' => true, 'saved' => $pretty));
}

// Fallback: method not allowed or unknown action
json_response(array('ok' => false, 'error' => 'Unknown action or method'), 400);
