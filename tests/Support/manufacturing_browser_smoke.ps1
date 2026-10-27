$ErrorActionPreference = 'Stop'
$workspacePath = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$previewPath = Join-Path $workspacePath 'storage/framework/testing/mfg-frontend-preview'
$profilePath = Join-Path $previewPath ('chrome-profile-' + [guid]::NewGuid().ToString('N'))
if (-not $profilePath.StartsWith($workspacePath + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Browser profile must remain in the workspace.' }
$chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
$chromeProcess = Start-Process -FilePath $chromePath -ArgumentList @('--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=8770', ('--user-data-dir="' + $profilePath + '"'), 'about:blank') -WindowStyle Hidden -PassThru
$socket = $null
$script:messageId = 0
function Invoke-Cdp([string]$method, $parameters) {
    $script:messageId++
    $request = @{id=$script:messageId;method=$method;params=$parameters} | ConvertTo-Json -Depth 25 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($request)
    $socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
    while ($true) {
        $memory = [IO.MemoryStream]::new()
        do {
            $buffer = New-Object byte[] 65536
            $message = $socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
            $memory.Write($buffer,0,$message.Count)
        } while (-not $message.EndOfMessage)
        $reply = [Text.Encoding]::UTF8.GetString($memory.ToArray()) | ConvertFrom-Json
        $memory.Dispose()
        if ($reply.id -eq $script:messageId) { if ($reply.error) { throw ($reply.error | ConvertTo-Json -Compress) }; return $reply.result }
    }
}
function Evaluate([string]$expression) {
    $result = Invoke-Cdp 'Runtime.evaluate' @{expression=$expression;returnByValue=$true;awaitPromise=$true}
    if ($result.exceptionDetails) { throw ($result.exceptionDetails | ConvertTo-Json -Depth 12 -Compress) }
    return $result.result.value
}
function Wait-For([string]$expression) {
    for ($attempt=0;$attempt -lt 60;$attempt++) { if (Evaluate $expression) { return }; Start-Sleep -Milliseconds 200 }
    throw ('Browser condition timed out: ' + $expression)
}
function Screenshot([string]$name) {
    $result = Invoke-Cdp 'Page.captureScreenshot' @{format='png';captureBeyondViewport=$false}
    [IO.File]::WriteAllBytes((Join-Path $previewPath $name), [Convert]::FromBase64String($result.data))
}
try {
    for ($attempt=0;$attempt -lt 40;$attempt++) { try { $tabs = Invoke-RestMethod 'http://127.0.0.1:8770/json/list'; break } catch { Start-Sleep -Milliseconds 250 } }
    $tab = $tabs | Where-Object type -eq 'page' | Select-Object -First 1
    $socket = [Net.WebSockets.ClientWebSocket]::new()
    $socket.ConnectAsync([uri]$tab.webSocketDebuggerUrl,[Threading.CancellationToken]::None).GetAwaiter().GetResult()
    Invoke-Cdp 'Page.enable' @{} | Out-Null
    Invoke-Cdp 'Runtime.enable' @{} | Out-Null
    Invoke-Cdp 'Emulation.setDeviceMetricsOverride' @{width=1440;height=1000;deviceScaleFactor=1;mobile=$false} | Out-Null
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8769/manufacturing/boms'} | Out-Null
    Wait-For 'typeof jQuery !== "undefined" && document.querySelectorAll("#mfg-table tbody tr").length === 1'
    Evaluate 'window.confirm = () => true; document.querySelector("[data-mfg-action=new-bom]").click(); true' | Out-Null
    Wait-For 'document.querySelectorAll("#mfg-bom-items tr").length === 1'
    Wait-For 'document.querySelector("#mfg-dialog .modal-dialog").getBoundingClientRect().top >= 25'
    Evaluate 'jQuery("#mfg-dialog-body [name=name]").val("Plastic bulb body recipe"); jQuery("#mfg-dialog-body [name=variation_id]").append(new Option("Plastic bulb body", 2, true, true)).trigger("change"); jQuery(".mfg-input-variation").append(new Option("Raw plastic pellets", 1, true, true)).trigger("change"); jQuery(".mfg-input-unit").val("2"); jQuery(".mfg-input-quantity").val("40"); true' | Out-Null
    Screenshot 'bom-desktop.png'
    Evaluate 'document.querySelector("#mfg-dialog-form").requestSubmit(); true' | Out-Null
    Wait-For 'document.querySelector(".mfg-form-errors").textContent.includes("Temporary fixture failure")'
    Evaluate 'document.querySelector("#mfg-dialog-form").requestSubmit(); true' | Out-Null
    Wait-For 'document.querySelector(".mfg-form-errors").textContent.includes("review the material quantities")'
    $calls = Invoke-RestMethod 'http://127.0.0.1:8769/__requests'
    if ($calls.Count -ne 2 -or $calls[0].key -ne $calls[1].key -or $calls[0].payload.items[0].quantity -isnot [string]) { throw 'Retry key or decimal string payload regression.' }
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8769/manufacturing/production-orders/1'} | Out-Null
    Wait-For 'document.querySelector("[data-mfg-action=start]") !== null'
    Evaluate 'window.confirm = () => true; document.querySelector("[data-mfg-action=start]").click(); true' | Out-Null
    Wait-For 'document.querySelectorAll("#mfg-issue-preview tr[data-variation]").length === 1'
    Wait-For 'document.querySelector("#mfg-dialog .modal-dialog").getBoundingClientRect().top >= 25'
    if (-not (Evaluate 'document.querySelector("#mfg-issue-preview").textContent.includes("KG")')) { throw 'Material inventory unit was not displayed.' }
    Screenshot 'production-desktop.png'
    Invoke-Cdp 'Emulation.setDeviceMetricsOverride' @{width=390;height=844;deviceScaleFactor=1;mobile=$true} | Out-Null
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8769/manufacturing/raw-materials'} | Out-Null
    Wait-For 'document.querySelectorAll("#mfg-table tbody tr").length === 1'
    if (Evaluate 'document.documentElement.scrollWidth > window.innerWidth + 1') { throw 'The mobile page overflows the viewport.' }
    Screenshot 'materials-mobile.png'
    Write-Output 'PASS: desktop BOM form, exact string payloads, retry key preservation, validation errors, production issues with units, mobile stock table and viewport.'
} finally {
    if ($socket -and $socket.State -eq [Net.WebSockets.WebSocketState]::Open) { try { Invoke-Cdp 'Browser.close' @{} | Out-Null } catch {}; $socket.Dispose() }
    try { Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8769/__shutdown' | Out-Null } catch {}
    if (-not $chromeProcess.HasExited) { $chromeProcess.WaitForExit(5000) | Out-Null }
}
