<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle File Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['files'])) {
    $folder_name = trim($_POST['folder_name']);

    if (empty($folder_name)) {
        echo "<script>alert('Please enter a folder name!');</script>";
    } else {
        foreach ($_FILES['files']['name'] as $key => $file_name) {
            $target_dir = $upload_dir . $folder_name . "/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $target_file = $target_dir . basename($file_name);

            if (move_uploaded_file($_FILES["files"]["tmp_name"][$key], $target_file)) {
                $sql = "INSERT INTO files (user_id, folder_name, file_name, file_path) VALUES ('$user_id', '$folder_name', '$file_name', '$target_file')";
                $conn->query($sql);
            } else {
                echo "<script>alert('Error uploading $file_name');</script>";
            }
        }
    }
}

// Handle File Deletion
if (isset($_GET['delete_file'])) {
    $file_id = $_GET['delete_file'];
    $query = "SELECT file_path FROM files WHERE id='$file_id' AND user_id='$user_id'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        unlink($file['file_path']);
        $conn->query("DELETE FROM files WHERE id='$file_id' AND user_id='$user_id'");
    }
    header("Location: dashboard.php");
    exit();
}

// Handle Folder Deletion
if (isset($_GET['delete_folder'])) {
    $folder_name = $_GET['delete_folder'];

    $query = "SELECT file_path FROM files WHERE folder_name='$folder_name' AND user_id='$user_id'";
    $result = $conn->query($query);

    while ($file = $result->fetch_assoc()) {
        unlink($file['file_path']);
    }

    rmdir($upload_dir . $folder_name);

    $conn->query("DELETE FROM files WHERE folder_name='$folder_name' AND user_id='$user_id'");
    header("Location: dashboard.php");
    exit();
}

// Handle Folder Download as ZIP
if (isset($_GET['download_folder'])) {
    $folder_name = $_GET['download_folder'];
    $zip_file = "downloads/$folder_name.zip";

    if (!is_dir("downloads")) {
        mkdir("downloads", 0777, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        $folder_path = $upload_dir . $folder_name;
        $files = glob($folder_path . "/*");

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=$folder_name.zip");
        header("Content-Length: " . filesize($zip_file));
        readfile($zip_file);
        unlink($zip_file);
        exit();
    }
}

// Fetch Folders
$sql = "SELECT folder_name FROM files WHERE user_id = '$user_id' GROUP BY folder_name ORDER BY MAX(id) DESC";
$folders = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_name; ?>'s Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .folder { margin-bottom: 10px; cursor: pointer; font-weight: bold; }
        .files { display: none; margin-left: 20px; }
        .delete-btn, .download-btn, .preview-btn { color: red; text-decoration: none; margin-left: 10px; }
        .preview-btn { color: blue; }
        .search-bar { margin-bottom: 20px; }
        .search-bar input { width: 98%; padding: 10px; font-size: 16px; }
        .highlight { background-color: black; color: white; } /* Highlight style */
    </style>
    <script>
        function toggleFiles(folderId) {
            var filesDiv = document.getElementById(folderId);
            filesDiv.style.display = (filesDiv.style.display === "none") ? "block" : "none";
        }

        function confirmDelete(type, id) {
            return confirm("Are you sure you want to delete this " + type + "?");
        }

        // Highlight folder names based on search query
        function highlightFolders() {
            const searchQuery = document.getElementById("searchInput").value.toLowerCase();
            const folders = document.querySelectorAll(".folder");

            folders.forEach(folder => {
                const folderName = folder.textContent.toLowerCase();
                const folderNameElement = folder.querySelector(".folder-name");

                // Remove previous highlights
                folderNameElement.innerHTML = folderNameElement.textContent;

                // Highlight matching text
                if (searchQuery && folderName.includes(searchQuery)) {
                    const regex = new RegExp(`(${searchQuery})`, "gi");
                    folderNameElement.innerHTML = folderNameElement.textContent.replace(regex, "<span class='highlight'>$1</span>");
                }
            });
        }
    </script>
</head>
<body>

    <div class="wrapper">
        <div class="sidebar">
            <h2>Welcome, <?php echo $user_name; ?> 👋</h2>
            <ul>
                <li><a href="dashboard.php">🏠 Dashboard</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <h1>Dashboard</h1>
            <p>Welcome <strong><?php echo $user_name; ?></strong>. Upload and manage your files.</p>

            <!-- File Upload Form -->
            <div class="upload-section">
                <h2>📤 Upload Files</h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="text" name="folder_name" placeholder="Enter folder name" required>
                    <input type="file" name="files[]" multiple required>
                    <button type="submit">Upload</button>
                </form>
            </div>

            <!-- Uploaded Files List -->
            <div class="uploaded-files">
                <h2>📂 Your Uploaded Folders</h2>
                <!-- Search Bar -->
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search folder names..." oninput="highlightFolders()">
                </div>
                <?php
                if ($folders->num_rows > 0) {
                    while ($folder = $folders->fetch_assoc()) {
                        $folder_name = $folder['folder_name'];
                        echo "<div class='folder' onclick=\"toggleFiles('$folder_name')\">
                              <span class='folder-name'>📁 $folder_name</span>
                              <a href='dashboard.php?delete_folder=$folder_name' class='delete-btn' onclick='return confirmDelete(\"folder\", \"$folder_name\")'>🗑 Delete</a>
                              <a href='dashboard.php?download_folder=$folder_name' class='download-btn'>⬇ Download</a></div>";
                        echo "<div class='files' id='$folder_name'>";

                        $file_sql = "SELECT * FROM files WHERE user_id = '$user_id' AND folder_name = '$folder_name' ORDER BY uploaded_at DESC";
                        $files = $conn->query($file_sql);

                        while ($file = $files->fetch_assoc()) {
                            echo "<li><a href='" . $file['file_path'] . "' download>📄 " . $file['file_name'] . "</a> 
                                  <a href='" . $file['file_path'] . "' class='preview-btn' target='_blank'>👁 Preview</a>
                                  <a href='dashboard.php?delete_file=" . $file['id'] . "' class='delete-btn' onclick='return confirmDelete(\"file\", \"" . $file['id'] . "\")'>🗑</a></li>";
                        }

                        echo "</div>";
                    }
                } else {
                    echo "<p>No files uploaded yet.</p>";
                }
                ?>
            </div>
        </div>
    </div>

</body>
</html>