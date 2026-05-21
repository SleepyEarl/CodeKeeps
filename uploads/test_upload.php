<?php

$file = "uploads/test.txt";

if (!is_dir("uploads")) {
    die("Uploads folder does not exist");
}

if (file_put_contents($file, "CodeKeep upload test successful!") !== false) {
    echo "UPLOAD WORKING ✔";
} else {
    echo "UPLOAD FAILED ❌";
}