<?php
// 配置管理员密码
$adminPassword = "admin123";

// 会话开始
session_start();

// 检查是否为管理员登录
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

// 登录处理
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['isAdmin'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $errorMessage = "密码错误，请重试。";
    }
}

// 登出处理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 获取当前目录
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$currentDirPath = __DIR__ . '/uploads/' . $currentDir;

// 确保目录存在
if (!is_dir($currentDirPath)) {
    mkdir($currentDirPath, 0777, true);
}

// 文件操作处理
if ($isAdmin) {
    // 删除文件/目录
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['path'])) {
        $itemPath = __DIR__ . '/uploads/' . urldecode($_GET['path']);
        
        if (is_file($itemPath)) {
            unlink($itemPath);
            $message = "文件已删除";
        } elseif (is_dir($itemPath)) {
            // 递归删除目录
            $dirIterator = new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $recursiveIterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::CHILD_FIRST);
            
            foreach ($recursiveIterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            
            rmdir($itemPath);
            $message = "目录已删除";
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
    
    // 重命名文件/目录
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'rename') {
        $oldPath = __DIR__ . '/uploads/' . urldecode($_POST['oldPath']);
        $newPath = __DIR__ . '/uploads/' . urldecode($_POST['newPath']);
        
        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
            $message = "已重命名";
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
    
    // 移动文件/目录
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'move') {
        $sourcePath = __DIR__ . '/uploads/' . urldecode($_POST['sourcePath']);
        $targetPath = __DIR__ . '/uploads/' . urldecode($_POST['targetPath']);
        
        if (file_exists($sourcePath)) {
            // 确保目标目录存在
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            rename($sourcePath, $targetPath);
            $message = "已移动";
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
}

// 新建目录处理
if ($isAdmin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['newDir'])) {
    $newDir = $_POST['newDir'];
    $newDirPath = $currentDirPath . '/' . $newDir;
    
    if (!is_dir($newDirPath)) {
        mkdir($newDirPath, 0777, true);
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
    exit;
}

// 文件上传处理
if ($isAdmin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $fileName = $_FILES['files']['name'][$key];
        $filePath = $currentDirPath . '/' . $fileName;
        
        // 检查文件是否已存在
        if (file_exists($filePath)) {
            $fileName = time() . '_' . $fileName; // 添加时间戳避免重名
            $filePath = $currentDirPath . '/' . $fileName;
        }
        
        if (move_uploaded_file($tmp_name, $filePath)) {
            $uploadMessage = "文件上传成功";
        } else {
            $uploadMessage = "文件上传失败";
        }
    }
}

// 获取当前目录下的文件和子目录
$items = [];
if (is_dir($currentDirPath)) {
    $dirIterator = new DirectoryIterator($currentDirPath);
    
    foreach ($dirIterator as $item) {
        if ($item->isDot()) continue;
        
        $itemInfo = [
            'name' => $item->getFilename(),
            'type' => $item->isDir() ? 'dir' : 'file',
            'mtime' => $item->getMTime(),
            'path' => $currentDir . ($currentDir ? '/' : '') . $item->getFilename()
        ];
        
        if ($item->isFile()) {
            $itemInfo['size'] = $item->getSize();
            $itemInfo['extension'] = $item->getExtension();
        }
        
        $items[] = $itemInfo;
    }
}

// 排序：目录在前，文件在后，按名称排序
usort($items, function($a, $b) {
    if ($a['type'] === $b['type']) {
        return strcasecmp($a['name'], $b['name']);
    }
    return ($a['type'] === 'dir') ? -1 : 1;
});

// 获取所有目录（用于移动操作）
function getAllDirectories($baseDir) {
    $dirs = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            $relativePath = str_replace($baseDir, '', $fileInfo->getPathname());
            $dirs[] = ltrim($relativePath, '/');
        }
    }
    
    return $dirs;
}

$allDirs = getAllDirectories(__DIR__ . '/uploads/');

// 生成面包屑导航
$breadcrumbs = [];
$pathParts = explode('/', $currentDir);
$currentPath = '';

foreach ($pathParts as $part) {
    if ($part === '') continue;
    
    $currentPath .= ($currentPath ? '/' : '') . $part;
    $breadcrumbs[] = [
        'name' => $part,
        'path' => $currentPath
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件管理系统</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .admin-panel { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .breadcrumbs { margin-bottom: 15px; }
        .breadcrumbs a { text-decoration: none; }
        .breadcrumbs a:hover { text-decoration: underline; }
        .file-list { width: 100%; border-collapse: collapse; }
        .file-list th, .file-list td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .file-list th { background-color: #f2f2f2; }
        .preview img { max-width: 100px; max-height: 100px; cursor: pointer; }
        .preview audio, .preview video { max-width: 200px; }
        .success { color: green; }
        .error { color: red; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
        }
        .close {
            color: #aaaaaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover, .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .actions button { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 消息提示 -->
        <?php if (isset($message)): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (isset($uploadMessage)): ?>
            <div class="success"><?php echo $uploadMessage; ?></div>
        <?php endif; ?>
        
        <!-- 面包屑导航 -->
        <div class="breadcrumbs">
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>">根目录</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                &nbsp;/&nbsp;
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?dir=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$isAdmin): ?>
            <div class="admin-panel">
                <h3>管理员登录</h3>
                <form method="post" action="">
                    <label for="password">密码：</label>
                    <input type="password" id="password" name="password" required>
                    <button type="submit">登录</button>
                    <?php if (isset($errorMessage)): ?>
                        <p class="error"><?php echo $errorMessage; ?></p>
                    <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-panel">
                <a href="?logout">退出登录</a>
                
                <h3>新建目录</h3>
                <form method="post" action="?dir=<?php echo urlencode($currentDir); ?>">
                    <input type="text" name="newDir" placeholder="目录名称" required>
                    <button type="submit">创建</button>
                </form>
                
                <h3>上传文件</h3>
                <form method="post" action="?dir=<?php echo urlencode($currentDir); ?>" enctype="multipart/form-data">
                    <input type="file" name="files[]" multiple required>
                    <button type="submit">上传到当前目录</button>
                </form>
            </div>
        <?php endif; ?>
        
        <h2><?php echo $currentDir ? htmlspecialchars($currentDir) : '根目录'; ?></h2>
        <table class="file-list">
            <thead>
                <tr>
                    <th>名称</th>
                    <th>类型</th>
                    <th>大小</th>
                    <th>修改时间</th>
                    <th>预览</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if ($item['type'] === 'dir'): ?>
                                <a href="?dir=<?php echo urlencode($item['path']); ?>"><?php echo htmlspecialchars($item['name']); ?>/</a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($item['name']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['type'] === 'dir' ? '目录' : '文件'; ?></td>
                        <td><?php echo $item['type'] === 'dir' ? '-' : formatSize($item['size']); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', $item['mtime']); ?></td>
                        <td class="preview">
                            <?php if ($item['type'] === 'file'): ?>
                                <?php
                                $fileUrl = 'uploads/' . $item['path'];
                                $ext = strtolower($item['extension']);
                                
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    echo "<img src='$fileUrl' alt='图片预览' onclick=\"openImageModal('$fileUrl')\">";
                                } elseif (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                                    echo "<video controls><source src='$fileUrl' type='video/$ext'>不支持的视频格式</video>";
                                } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
                                    echo "<audio controls><source src='$fileUrl' type='audio/$ext'>不支持的音频格式</audio>";
                                } else {
                                    echo "-";
                                }
                                ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($isAdmin): ?>
                                <button onclick="openRenameModal('<?php echo $item['path']; ?>', '<?php echo htmlspecialchars($item['name']); ?>')">重命名</button>
                                <button onclick="openMoveModal('<?php echo $item['path']; ?>', '<?php echo htmlspecialchars($item['name']); ?>')">移动</button>
                                <button onclick="if(confirm('确定要删除吗？')) window.location='?dir=<?php echo urlencode($currentDir); ?>&action=delete&path=<?php echo urlencode($item['path']); ?>'">删除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- 图片查看模态框 -->
        <div id="imageModal" class="modal">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img class="modal-content" id="modalImage">
        </div>
        
        <!-- 重命名模态框 -->
        <div id="renameModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeRenameModal()">&times;</span>
                <h3>重命名</h3>
                <form method="post" action="?dir=<?php echo urlencode($currentDir); ?>">
                    <input type="hidden" id="renameOldPath" name="oldPath">
                    <input type="hidden" name="action" value="rename">
                    <p>新名称：<input type="text" id="renameNewName" name="newPath" required></p>
                    <button type="submit">确认</button>
                    <button type="button" onclick="closeRenameModal()">取消</button>
                </form>
            </div>
        </div>
        
        <!-- 移动模态框 -->
        <div id="moveModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeMoveModal()">&times;</span>
                <h3>移动</h3>
                <form method="post" action="?dir=<?php echo urlencode($currentDir); ?>">
                    <input type="hidden" id="moveSourcePath" name="sourcePath">
                    <input type="hidden" name="action" value="move">
                    <p>文件：<span id="moveFileName"></span></p>
                    <p>目标目录：
                        <select id="moveTargetDir" name="targetPath" required>
                            <option value="">选择目录</option>
                            <?php foreach ($allDirs as $dir): ?>
                                <option value="<?php echo $dir . '/' . urlencode('__FILENAME__'); ?>"><?php echo $dir ?: '根目录'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <button type="submit">确认</button>
                    <button type="button" onclick="closeMoveModal()">取消</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // 图片查看模态框功能
        function openImageModal(imgUrl) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImage.src = imgUrl;
        }
        
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
        }
        
        // 重命名模态框功能
        function openRenameModal(path, name) {
            const modal = document.getElementById('renameModal');
            const oldPathInput = document.getElementById('renameOldPath');
            const newNameInput = document.getElementById('renameNewName');
            
            oldPathInput.value = path;
            newNameInput.value = name;
            
            // 生成新路径（自动替换文件名部分）
            newNameInput.oninput = function() {
                const pathParts = path.split('/');
                pathParts[pathParts.length - 1] = this.value;
                document.querySelector('input[name="newPath"]').value = pathParts.join('/');
            };
            
            modal.style.display = 'block';
        }
        
        function closeRenameModal() {
            const modal = document.getElementById('renameModal');
            modal.style.display = 'none';
        }
        
        // 移动模态框功能
        function openMoveModal(path, name) {
            const modal = document.getElementById('moveModal');
            const sourcePathInput = document.getElementById('moveSourcePath');
            const fileNameSpan = document.getElementById('moveFileName');
            const targetDirSelect = document.getElementById('moveTargetDir');
            
            sourcePathInput.value = path;
            fileNameSpan.textContent = name;
            
            // 为每个选项设置正确的文件名
            const options = targetDirSelect.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value) {
                    options[i].value = options[i].value.replace('__FILENAME__', name);
                }
            }
            
            modal.style.display = 'block';
        }
        
        function closeMoveModal() {
            const modal = document.getElementById('moveModal');
            modal.style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modals = [
                document.getElementById('imageModal'),
                document.getElementById('renameModal'),
                document.getElementById('moveModal')
            ];
            
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
    
    <?php
    // 辅助函数：格式化文件大小
    function formatSize($bytes) {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
    ?>
</body>
</html>
