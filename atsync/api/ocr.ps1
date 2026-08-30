param([string]$imagePath)

try {
    [System.Reflection.Assembly]::LoadWithPartialName('System.Runtime.WindowsRuntime') | Out-Null
    [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
    [Windows.Media.Ocr.OcrEngine, Windows.Foundation.UniversalApiContract, ContentType = WindowsRuntime] | Out-Null
    [Windows.Graphics.Imaging.BitmapDecoder, Windows.Foundation.UniversalApiContract, ContentType = WindowsRuntime] | Out-Null

    $fullPath = (System.IO.Path::GetFullPath($imagePath))
    $fileTask = [Windows.Storage.StorageFile]::GetFileFromPathAsync($fullPath)
    $file = [System.WindowsRuntimeSystemExtensions]::GetAwaiter($fileTask).GetResult()

    $streamTask = $file.OpenAsync([Windows.Storage.FileAccessMode]::Read)
    $stream = [System.WindowsRuntimeSystemExtensions]::GetAwaiter($streamTask).GetResult()

    $decoderTask = [Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)
    $decoder = [System.WindowsRuntimeSystemExtensions]::GetAwaiter($decoderTask).GetResult()

    $bitmapTask = $decoder.GetSoftwareBitmapAsync()
    $bitmap = [System.WindowsRuntimeSystemExtensions]::GetAwaiter($bitmapTask).GetResult()

    $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage([Windows.Globalization.Language]::new('en-US'))
    if (-not $engine) {
        $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
    }

    $ocrTask = $engine.RecognizeAsync($bitmap)
    $result = [System.WindowsRuntimeSystemExtensions]::GetAwaiter($ocrTask).GetResult()

    Write-Output $result.Text
} catch {
    Write-Output ""
}
