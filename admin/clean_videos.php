<?php
define('IN_CHAT', true);
require_once '../config.php';
// 管理员权限校验
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit;
}
$uploadPath = '../uploads/';
$videoMimeExts = ['mp4','mov','avi','mpeg','webm'];
$msg = '';
$logLines = [];
$now = date('Y-m-d H:i:s');
// 获取排序参数
$sortType = isset($_GET['sort']) ? $_GET['sort'] : 'time';
$allowSort = ['time','size','name'];
if (!in_array($sortType, $allowSort)) $sortType = 'time';
// 日期筛选参数接收
$startDate = isset($_GET['start']) ? trim($_GET['start']) : '';
$endDate = isset($_GET['end']) ? trim($_GET['end']) : '';
$quickFilter = isset($_GET['quick']) ? trim($_GET['quick']) : '';
$allowQuick = ['7','14','30','over30'];
if (!in_array($quickFilter, $allowQuick)) $quickFilter = '';
// 处理删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 单文件删除
    if (!empty($_POST['del_file'])) {
        $file = $_POST['del_file'];
        $fullPath = $uploadPath . basename($file);
        if (is_file($fullPath)) {
            unlink($fullPath);
            $msg = "成功删除视频文件：" . basename($file);
            $logLines[] = "[$now] 删除视频：" . basename($file);
        } else {
            $msg = "文件不存在，删除失败";
            $logLines[] = "[$now] 删除失败：文件不存在";
        }
    }
    // 批量框选删除
    if (!empty($_POST['batch_files']) && is_array($_POST['batch_files'])) {
        $batchDelCount = 0;
        foreach ($_POST['batch_files'] as $fileName) {
            $safeName = basename($fileName);
            $fullPath = $uploadPath . $safeName;
            if (is_file($fullPath)) {
                unlink($fullPath);
                $batchDelCount++;
            }
        }
        $msg = "批量选中删除完成，共删除 {$batchDelCount} 个视频文件";
        $logLines[] = "[$now] 多选批量删除视频，删除总数：{$batchDelCount}";
    }
    // 一键清空所有视频
    if (isset($_POST['clear_all'])) {
        $delCount = 0;
        if (is_dir($uploadPath)) {
            $dir = new DirectoryIterator($uploadPath);
            foreach ($dir as $f) {
                if ($f->isFile()) {
                    $ext = strtolower($f->getExtension());
                    if (in_array($ext, $videoMimeExts)) {
                        unlink($f->getPathname());
                        $delCount++;
                    }
                }
            }
        }
        $msg = "批量清理完成，共删除 {$delCount} 个视频文件";
        $logLines[] = "[$now] 批量清理上传视频，删除总数：{$delCount}";
    }
}
// 统计视频信息
$videoList = [];
$totalSize = 0;
$videoCount = 0;
if (is_dir($uploadPath)) {
    $dir = new DirectoryIterator($uploadPath);
    foreach ($dir as $fileinfo) {
        if ($fileinfo->isFile()) {
            $ext = strtolower($fileinfo->getExtension());
            if (in_array($ext, $videoMimeExts)) {
                $mtime = $fileinfo->getMTime();
                $matchDate = true;
                // 快捷日期筛选
                if ($quickFilter !== '') {
                    $today = strtotime(date('Y-m-d'));
                    $fileDay = strtotime(date('Y-m-d', $mtime));
                    $diffDay = floor(($today - $fileDay) / 86400);
                    switch ($quickFilter) {
                        case '7': $matchDate = $diffDay <= 7; break;
                        case '14': $matchDate = $diffDay <= 14; break;
                        case '30': $matchDate = $diffDay <= 30; break;
                        case 'over30': $matchDate = $diffDay > 30; break;
                    }
                }
                // 自定义起止日期筛选
                if ($startDate !== '' && $endDate !== '' && $matchDate) {
                    $s = strtotime($startDate . ' 00:00:00');
                    $e = strtotime($endDate . ' 23:59:59');
                    if (!($mtime >= $s && $mtime <= $e)) $matchDate = false;
                } elseif ($startDate !== '' && $matchDate) {
                    $s = strtotime($startDate . ' 00:00:00');
                    if ($mtime < $s) $matchDate = false;
                } elseif ($endDate !== '' && $matchDate) {
                    $e = strtotime($endDate . ' 23:59:59');
                    if ($mtime > $e) $matchDate = false;
                }
                if (!$matchDate) continue;
                $videoCount++;
                $size = $fileinfo->getSize();
                $totalSize += $size;
                $videoList[] = [
                    'name' => $fileinfo->getFilename(),
                    'size' => $size,
                    'mtime' => $mtime,
                    'show_time' => date('Y-m-d H:i', $mtime)
                ];
            }
        }
    }
}
// 排序
switch ($sortType) {
    case 'size':
        usort($videoList, function($a, $b) {
            return $b['size'] - $a['size'];
        });
        break;
    case 'name':
        usort($videoList, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        break;
    default:
        usort($videoList, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });
        break;
}
// 文件大小格式化
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024,2) . ' KB';
    return round($bytes/1048576,2) . ' MB';
}
$sizeText = formatSize($totalSize);
$logLines[] = "[$now] 进入视频清理面板";
$logLines[] = "[$now] 上传目录：{$uploadPath}";
$logLines[] = "[$now] 当前筛选后视频总数：{$videoCount} 个，占用空间：{$sizeText}";
$sortText = [
    'time' => '修改时间(默认)',
    'size' => '文件大小',
    'name' => '文件名'
][$sortType];
$logLines[] = "[$now] 当前排序方式：{$sortText}";
if ($quickFilter !== '') {
    $quickText = ['7'=>'近7天','14'=>'近14天','30'=>'近30天','over30'=>'30天以前'][$quickFilter];
    $logLines[] = "[$now] 启用快捷日期筛选：{$quickText}";
}
if ($startDate !== '' || $endDate !== '') {
    $logLines[] = "[$now] 自定义日期筛选：起始{$startDate}，结束{$endDate}";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>视频清理 - 管理后台</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F8F9FA;color:#212529}
.wrap{display:flex;min-height:100vh}
.sidebar{width:240px;background:#212529;padding:24px 0;flex-shrink:0}
.side-title{color:#fff;text-align:center;margin-bottom:24px;font-size:18px}
.side-menu{list-style:none;padding:0}
.side-item{margin-bottom:4px}
.side-link{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#e9ecef;text-decoration:none;border-radius:8px;margin:0 8px}
.side-link:hover{background:#343a40;color:#fff}
.side-link.active{background:#12B886;color:#fff}
.main{flex:1;padding:24px}
.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.page-title{font-size:22px;font-weight:600}
.time{color:#6c757d}
.card-box{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);border-left:4px solid #12B886}
.card-title{font-size:14px;color:#6c757d;margin-bottom:8px}
.card-num{font-size:24px;font-weight:700;color:#212529}
.card-icon{font-size:22px;color:#12B886;opacity:0.8}
.card-body{display:flex;justify-content:space-between;align-items:center}
.opt-bar{margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.btn{padding:10px 18px;border:none;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block}
.btn-danger{background:#dc3545;color:#fff}
.btn-primary{background:#12B886;color:#fff}
.btn-outline{background:#fff;color:#212529;border:1px solid #ddd}
.btn-outline.active{background:#12B886;color:#fff;border-color:#12B886}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;background:#d1e7dd;color:#0f5132;border:1px solid #badbcc}
.table-box{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:24px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 8px;text-align:left;border-bottom:1px solid #eee}
th{color:#6c757d;font-weight:500}
.del-btn{color:#dc3545;border:none;background:none;cursor:pointer}
.log-box{background:#1e1e1e;color:#dcdcdc;border-radius:12px;padding:20px; height:280px;overflow-y:auto;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.6}
.log-item{white-space:pre}
.filter-input{padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:14px}
input[type="checkbox"]{width:18px;height:18px;cursor:pointer;}
@media (max-width:768px){
    .wrap{flex-direction:column}
    .sidebar{width:100%}
    .card-box{grid-template-columns:repeat(2,1fr)}
    th,td{padding:10px 4px;font-size:13px}
    input[type="checkbox"]{width:22px;height:22px;}
    .filter-input{width:130px}
}
</style>
</head>
<body>
<div class="wrap">
    <div class="sidebar">
        <h3 class="side-title">管理后台</h3>
        <ul class="side-menu">
            <li class="side-item"><a href="index.php" class="side-link"><i class="fas fa-tachometer-alt"></i> 仪表盘</a></li>
            <li class="side-item"><a href="users.php" class="side-link"><i class="fas fa-users"></i> 用户管理</a></li>
            <li class="side-item"><a href="ip_blacklist.php" class="side-link"><i class="fas fa-ban"></i> IP封禁</a></li>
            <li class="side-item"><a href="clean_uploads.php" class="side-link"><i class="fas fa-image"></i> 图片清理</a></li>
            <li class="side-item"><a href="clean_videos.php" class="side-link active"><i class="fas fa-video"></i> 视频清理</a></li>
            <li class="side-item"><a href="clean_music.php" class="side-link"><i class="fas fa-music"></i> 音乐音频清理</a></li>
            <li class="side-item"><a href="../chat.php" class="side-link"><i class="fas fa-comments"></i> 返回聊天室</a></li>
        </ul>
    </div>
    <div class="main">
        <div class="page-head">
            <div class="page-title">上传视频清理</div>
            <div class="time"><i class="far fa-clock"></i> <?php echo date('Y-m-d H:i:s') ?></div>
        </div>
        <?php if (!empty($msg)): ?>
            <div class="alert"><?php echo htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <div class="opt-bar">
            <form id="filterForm" method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortType) ?>">
                <span>日期筛选：</span>
                <input class="filter-input" type="date" name="start" value="<?php echo htmlspecialchars($startDate) ?>">
                <span>~</span>
                <input class="filter-input" type="date" name="end" value="<?php echo htmlspecialchars($endDate) ?>">
                <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> 筛选</button>
                <button type="button" class="btn btn-outline <?php echo $quickFilter=='7'?'active':'' ?>" onclick="setQuick('7')">近7天</button>
                <button type="button" class="btn btn-outline <?php echo $quickFilter=='14'?'active':'' ?>" onclick="setQuick('14')">近14天</button>
                <button type="button" class="btn btn-outline <?php echo $quickFilter=='30'?'active':'' ?>" onclick="setQuick('30')">近30天</button>
                <button type="button" class="btn btn-outline <?php echo $quickFilter=='over30'?'active':'' ?>" onclick="setQuick('over30')">30天以前</button>
                <a href="?sort=<?php echo $sortType ?>" class="btn btn-outline"><i class="fas fa-redo"></i> 重置筛选</a>
            </form>
        </div>
        <div class="card-box">
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">筛选后视频总数</div>
                        <div class="card-num"><?php echo $videoCount ?></div>
                    </div>
                    <i class="fas fa-video card-icon"></i>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div>
                        <div class="card-title">筛选后总占用空间</div>
                        <div class="card-num"><?php echo $sizeText ?></div>
                    </div>
                    <i class="fas fa-hdd card-icon"></i>
                </div>
            </div>
        </div>
        <div class="opt-bar">
            <form method="post" onsubmit="return confirm('确认删除全部视频？此操作不可恢复！')">
                <button type="submit" name="clear_all" class="btn btn-danger"><i class="fas fa-trash-alt"></i> 一键清空所有视频</button>
            </form>
            <form id="batchForm" method="post" onsubmit="return confirm('确定删除选中的视频？不可恢复！')">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check-square"></i> 删除选中项</button>
            </form>
            <button type="button" class="btn btn-outline" onclick="selectAll()"><i class="fas fa-square"></i> 全选</button>
            <button type="button" class="btn btn-outline" onclick="reverseSelect()"><i class="fas fa-exchange-alt"></i> 反向选择</button>
            <button id="cancelSelectBtn" type="button" class="btn btn-outline" style="display:none;" onclick="clearSelect()"><i class="fas fa-times"></i> 取消选择</button>
            <div>
                <span style="margin-right:8px;">排序：</span>
                <a href="?sort=time&start=<?php echo urlencode($startDate) ?>&end=<?php echo urlencode($endDate) ?>&quick=<?php echo urlencode($quickFilter) ?>" class="btn btn-outline <?php echo $sortType=='time'?'active':'' ?>">修改时间(默认)</a>
                <a href="?sort=size&start=<?php echo urlencode($startDate) ?>&end=<?php echo urlencode($endDate) ?>&quick=<?php echo urlencode($quickFilter) ?>" class="btn btn-outline <?php echo $sortType=='size'?'active':'' ?>">按大小</a>
                <a href="?sort=name&start=<?php echo urlencode($startDate) ?>&end=<?php echo urlencode($endDate) ?>&quick=<?php echo urlencode($quickFilter) ?>" class="btn btn-outline <?php echo $sortType=='name'?'active':'' ?>">按文件名</a>
            </div>
        </div>
        <div class="table-box">
            <?php if (empty($videoList)): ?>
                <p>暂无匹配筛选条件的上传视频</p>
            <?php else: ?>
            <table>
                <tr>
                    <th><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                    <th>文件名</th>
                    <th>大小</th>
                    <th>最后修改时间</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($videoList as $item): ?>
                <tr>
                    <td><input type="checkbox" form="batchForm" name="batch_files[]" class="item-check" value="<?php echo htmlspecialchars($item['name']) ?>"></td>
                    <td><?php echo htmlspecialchars($item['name']) ?></td>
                    <td><?php echo formatSize($item['size']) ?></td>
                    <td><?php echo $item['show_time'] ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('确定删除该视频？')">
                            <input type="hidden" name="del_file" value="<?php echo htmlspecialchars($item['name']) ?>">
                            <button class="del-btn"><i class="fas fa-trash"></i> 删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <div class="page-title mb-2">操作日志</div>
        <div class="log-box">
<?php foreach($logLines as $line): ?>
<div class="log-item"><?php echo htmlspecialchars($line) ?></div>
<?php endforeach; ?>
        </div>
    </div>
</div>
<script>
function getCheckItems(){return document.querySelectorAll('.item-check');}
function refreshCancelBtn(){
    const items=getCheckItems();
    const cancelBtn=document.getElementById('cancelSelectBtn');
    let hasChecked=false;
    items.forEach(i=>{if(i.checked)hasChecked=true;});
    cancelBtn.style.display=hasChecked?'inline-block':'none';
}
function toggleAll(ck){
    getCheckItems().forEach(i=>i.checked=ck.checked);
    refreshCancelBtn();
}
function selectAll(){
    const items=getCheckItems();
    const ck=document.getElementById('checkAll');
    items.forEach(i=>i.checked=true);
    ck.checked=true;
    refreshCancelBtn();
}
function reverseSelect(){
    const items=getCheckItems();
    const ck=document.getElementById('checkAll');
    let all=true;
    items.forEach(i=>{i.checked=!i.checked;if(!i.checked)all=false;});
    ck.checked=all;
    refreshCancelBtn();
}
function clearSelect(){
    const items=getCheckItems();
    const ck=document.getElementById('checkAll');
    items.forEach(i=>i.checked=false);
    ck.checked=false;
    refreshCancelBtn();
}
document.addEventListener('change',e=>{
    if(e.target.classList.contains('item-check')){
        const items=getCheckItems();
        const ck=document.getElementById('checkAll');
        let all=true;
        items.forEach(i=>{if(!i.checked)all=false;});
        ck.checked=all;
        refreshCancelBtn();
    }
});
function setQuick(type){
    const form=document.getElementById('filterForm');
    const sortVal=form.querySelector('input[name="sort"]').value;
    const start=form.querySelector('input[name="start"]').value;
    const end=form.querySelector('input[name="end"]').value;
    location.href=`?sort=${sortVal}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&quick=${type}`;
}
</script>
</body>
</html>
