# Renderiza los PDF con el motor PDF de Windows, sin instalar dependencias.
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null = [Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime]
$null = [Windows.Data.Pdf.PdfDocument, Windows.Data.Pdf, ContentType = WindowsRuntime]
$null = [Windows.Data.Pdf.PdfPageRenderOptions, Windows.Data.Pdf, ContentType = WindowsRuntime]
$null = [Windows.Storage.Streams.InMemoryRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime]
$null = [Windows.Storage.Streams.DataReader, Windows.Storage.Streams, ContentType = WindowsRuntime]
$metodos = [System.WindowsRuntimeSystemExtensions].GetMethods()
$convertirOperacion = $metodos | Where-Object { $_.Name -eq 'AsTask' -and $_.IsGenericMethodDefinition -and $_.GetGenericArguments().Count -eq 1 -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
$convertirAccion = $metodos | Where-Object { $_.Name -eq 'AsTask' -and -not $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction' } | Select-Object -First 1
function Esperar-Operacion($Operacion, [Type]$Tipo) {
    $tareaPdf = $convertirOperacion.MakeGenericMethod($Tipo).Invoke($null, @($Operacion))
    $tareaPdf.Wait()
    return $tareaPdf.Result
}
$carpetaDocs = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$raizProyecto = [IO.Path]::GetFullPath((Join-Path $carpetaDocs '..'))
$carpetaSalida = Join-Path $raizProyecto 'frontend/test-results/pdf-inspeccion'
New-Item -ItemType Directory -Force -Path $carpetaSalida | Out-Null
foreach ($entradaPdf in (Get-Content -LiteralPath (Join-Path $carpetaDocs 'documentos.json') -Raw -Encoding UTF8 | ConvertFrom-Json).documentos) {
    $rutaPdf = Join-Path $carpetaDocs $entradaPdf.pdf
    $archivoPdf = Esperar-Operacion ([Windows.Storage.StorageFile]::GetFileFromPathAsync($rutaPdf)) ([Windows.Storage.StorageFile])
    $documentoPdf = Esperar-Operacion ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($archivoPdf)) ([Windows.Data.Pdf.PdfDocument])
    $paginasPdf = @(0, 1, ([int]$documentoPdf.PageCount - 1))
    if ($documentoPdf.PageCount -gt 10) { $paginasPdf += @(6, 8) }
    foreach ($indicePdf in ($paginasPdf | Sort-Object -Unique)) {
        $paginaPdf = $documentoPdf.GetPage([uint32]$indicePdf)
        $flujoPdf = New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
        $opcionesPdf = New-Object Windows.Data.Pdf.PdfPageRenderOptions
        $opcionesPdf.DestinationWidth = 1200
        $tareaRender = $convertirAccion.Invoke($null, @($paginaPdf.RenderToStreamAsync($flujoPdf, $opcionesPdf)))
        $tareaRender.Wait()
        $flujoPdf.Seek(0)
        $lectorPdf = New-Object Windows.Storage.Streams.DataReader($flujoPdf)
        $null = Esperar-Operacion ($lectorPdf.LoadAsync([uint32]$flujoPdf.Size)) ([uint32])
        $bytesPdf = New-Object byte[] ([int]$flujoPdf.Size)
        $lectorPdf.ReadBytes($bytesPdf)
        $nombrePdf = $entradaPdf.nombre + '-' + ($indicePdf + 1) + '.png'
        [IO.File]::WriteAllBytes((Join-Path $carpetaSalida $nombrePdf), $bytesPdf)
        $lectorPdf.Dispose(); $flujoPdf.Dispose(); $paginaPdf.Dispose()
    }
    Write-Output ($entradaPdf.pdf + ': ' + $documentoPdf.PageCount + ' paginas abiertas correctamente por Windows PDF.')
}
