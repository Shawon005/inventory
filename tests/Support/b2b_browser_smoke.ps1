param([switch]$CustomerOnly, [string]$CustomerSearch = 'Buyer', [string]$ExpectedCustomerId = '1')
$ErrorActionPreference = 'Stop'
$workspacePath = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$previewPath = Join-Path $workspacePath 'storage/framework/testing/b2b-frontend-preview'
$profilePath = Join-Path $previewPath ('chrome-profile-' + [guid]::NewGuid().ToString('N'))
if (-not $profilePath.StartsWith($workspacePath + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Browser profile must remain in the workspace.' }
$chromeProcess = Start-Process -FilePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -ArgumentList @('--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=8782', ('--user-data-dir="' + $profilePath + '"'), 'about:blank') -WindowStyle Hidden -PassThru
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
    for ($attempt=0;$attempt -lt 40;$attempt++) { try { $tabs = Invoke-RestMethod 'http://127.0.0.1:8782/json/list'; break } catch { Start-Sleep -Milliseconds 250 } }
    $tab = $tabs | Where-Object type -eq 'page' | Select-Object -First 1
    $socket = [Net.WebSockets.ClientWebSocket]::new()
    $socket.ConnectAsync([uri]$tab.webSocketDebuggerUrl,[Threading.CancellationToken]::None).GetAwaiter().GetResult()
    Invoke-Cdp 'Page.enable' @{} | Out-Null
    Invoke-Cdp 'Runtime.enable' @{} | Out-Null
    Invoke-Cdp 'Emulation.setDeviceMetricsOverride' @{width=1440;height=1100;deviceScaleFactor=1;mobile=$false} | Out-Null
    if ($CustomerOnly) {
        Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8781/b2b/pos'} | Out-Null
        Wait-For 'typeof jQuery!=="undefined" && !!jQuery("#b2b-sale-customer").data("select2") && document.readyState==="complete"'
        if (Evaluate 'jQuery("#b2b-sale-customer").data("select2").$container.hasClass("select2-hidden-accessible") || jQuery("#b2b-sale-customer").data("select2").$container[0].getBoundingClientRect().width<100') { throw 'Customer control is hidden by duplicate Select2 initialization.' }
        Evaluate 'jQuery(".tour, .tour-backdrop").remove();true' | Out-Null
        Evaluate 'jQuery("#b2b-sale-customer").select2("open");true' | Out-Null
        $searchJson = ConvertTo-Json -InputObject $CustomerSearch -Compress
        Evaluate ('jQuery(".select2-search__field").val(' + $searchJson + ').trigger("input");true') | Out-Null
        Wait-For 'document.querySelectorAll(".select2-results__option[aria-selected]").length>0'
        Evaluate 'jQuery(".select2-results__option[aria-selected]").first().trigger("mouseup");true' | Out-Null
        if ((Evaluate 'document.querySelector("#b2b-sale-customer").value') -ne $ExpectedCustomerId) { throw 'Saved customer did not appear in the searchable POS selector.' }
        if ((Evaluate 'performance.getEntriesByType("resource").filter(e=>e.name.includes("/data/customers")).length') -ne 0) { throw 'Customer selector unexpectedly depends on a background lookup.' }
        Screenshot 'customer-diagnostic.png'
        Write-Output 'PASS: saved customer is loaded with the full POS page, can be searched and selected, and requires no customer AJAX request.'
        return
    }
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8781/b2b/products'} | Out-Null
    Wait-For 'typeof jQuery !== "undefined" && document.querySelectorAll("#b2b-table tbody tr").length === 1'
    Evaluate 'window.confirm=()=>true;jQuery("#b2b-location").val("1").trigger("change");true' | Out-Null
    Wait-For 'document.querySelector("[data-b2b-action=transfer-product]")!==null'
    Evaluate 'document.querySelector("[data-b2b-action=transfer-product]").click();true' | Out-Null
    Wait-For 'document.querySelectorAll("#b2b-transfer-lines tr").length===1 && document.querySelector(".b2b-transfer-product").value!==""'
    Evaluate 'jQuery(".b2b-transfer-quantity").val("7");true' | Out-Null
    Screenshot 'transfer-desktop.png'
    Evaluate 'document.querySelector("#b2b-dialog-form").requestSubmit();true' | Out-Null
    Wait-For 'document.querySelector("#b2b-form-errors").textContent.includes("Temporary fixture failure")'
    Evaluate 'document.querySelector("#b2b-dialog-form").requestSubmit();true' | Out-Null
    Wait-For 'document.querySelector("#b2b-feedback").textContent.includes("B2B-T-FIXTURE")'
    $calls = Invoke-RestMethod 'http://127.0.0.1:8781/__requests'
    if ($calls.Count -ne 2 -or $calls[0].key -ne $calls[1].key -or $calls[1].payload.items[0].quantity -isnot [string]) { throw 'Transfer retry key or string quantity failed.' }
    if ($calls[1].payload.items[0].PSObject.Properties.Name -contains 'unit_price') { throw 'Transfer must not carry a selling price.' }
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8781/b2b/pos'} | Out-Null
    Wait-For 'typeof jQuery!=="undefined" && document.querySelector("[data-b2b-action=add-cart]")!==null'
    if (Evaluate 'jQuery("#b2b-sale-customer").data("select2").$container.hasClass("select2-hidden-accessible") || jQuery("#b2b-sale-customer").data("select2").$container[0].getBoundingClientRect().width<100') { throw 'Customer control is hidden by duplicate Select2 initialization.' }
    Evaluate 'jQuery("#b2b-sale-customer").select2("open");true' | Out-Null
    Wait-For 'document.querySelectorAll(".select2-results__option[aria-selected]").length>0'
    Evaluate 'jQuery(".select2-results__option[aria-selected]").first().trigger("mouseup");true' | Out-Null
    if ((Evaluate 'document.querySelector("#b2b-sale-customer").value') -ne '1') { throw 'Customer dropdown did not load/select its AJAX result.' }
    Evaluate 'window.confirm=()=>true;jQuery("#b2b-location").val("1").trigger("change");true' | Out-Null
    Wait-For '!document.querySelector("[data-b2b-action=add-cart]").disabled'
    Evaluate 'document.querySelector("[data-b2b-action=add-cart]").click();true' | Out-Null
    Wait-For 'document.querySelector(".b2b-price")!==null'
    if ((Evaluate 'document.querySelector(".b2b-price").value') -ne '') { throw 'B2B price must start blank.' }
    Evaluate 'jQuery(".b2b-cart-quantity").val("3").trigger("input");jQuery(".b2b-price").val("90.1234").trigger("input");document.querySelector("[data-b2b-action=pay-full]").click();true' | Out-Null
    if ((Evaluate 'document.querySelector("#b2b-cart-total").textContent') -ne '270.3702') { throw 'Manual decimal total is incorrect.' }
    Screenshot 'pos-desktop.png'
    Evaluate 'document.querySelector("#b2b-sale-form").requestSubmit();true' | Out-Null
    Wait-For 'document.querySelector("#b2b-feedback").textContent.includes("B2B-INV-FIXTURE")'
    $calls = Invoke-RestMethod 'http://127.0.0.1:8781/__requests'
    if ($calls.Count -ne 3 -or $calls[2].payload.items[0].unit_price -ne '90.1234' -or $calls[2].payload.items[0].unit_price -isnot [string] -or $calls[2].payload.payments[0].amount -ne '270.3702') { throw 'Checkout did not preserve the manually entered decimal price.' }
    Invoke-Cdp 'Emulation.setDeviceMetricsOverride' @{width=390;height=844;deviceScaleFactor=1;mobile=$true} | Out-Null
    Invoke-Cdp 'Page.navigate' @{url='http://127.0.0.1:8781/b2b/pos'} | Out-Null
    Wait-For 'document.querySelectorAll("#b2b-table tbody tr").length===1'
    if (Evaluate 'document.documentElement.scrollWidth>window.innerWidth+1') { throw 'Mobile B2B POS overflows the viewport.' }
    Screenshot 'pos-mobile.png'
    Write-Output 'PASS: visible searchable customer selector after shared initialization, transfer retry, blank B2B price, exact manual price checkout, invoice link, and mobile viewport.'
} finally {
    if ($socket -and $socket.State -eq [Net.WebSockets.WebSocketState]::Open) { try { Invoke-Cdp 'Browser.close' @{} | Out-Null } catch {}; $socket.Dispose() }
    try { Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8781/__shutdown' | Out-Null } catch {}
    if (-not $chromeProcess.HasExited) { $chromeProcess.WaitForExit(5000) | Out-Null }
}
