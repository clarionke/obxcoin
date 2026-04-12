$ErrorActionPreference = 'Stop'
$root = 'c:\xampp\htdocs\obxcoin'
$files = Get-ChildItem -Path $root -Recurse -Include '*.blade.php','*.php','*.json' |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\storage\\' }
$count = 0
foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $new = $content -replace '(?i)Default Coin', 'OBXCoin'
    if ($content -ne $new) {
        [System.IO.File]::WriteAllText($file.FullName, $new)
        Write-Host "Updated: $($file.FullName)"
        $count++
    }
}
Write-Host "--- Done. $count files updated."
