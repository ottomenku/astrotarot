# Reverse-tunnels this PC's Ollama to the VPS loopback.
# On the server Laravel then uses http://127.0.0.1:11434/v1
# The model is never exposed on the public internet.
#
# Keep this window open while production should use the local LLM.
# If the PC sleeps or this script stops, readings fall back to OpenAI
# when LLM_FALLBACK_OPENAI=true.

param(
    [string]$SshHost = "otto@194.182.83.30",
    [string]$IdentityFile = "$env:USERPROFILE\.ssh\id_ed25519",
    [int]$Port = 11434
)

$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

$ollama = curl.exe -sS -o NUL -w "%{http_code}" --max-time 3 "http://127.0.0.1:$Port/api/tags"
if ($ollama -ne "200") {
    Write-Error "Ollama is not reachable at http://127.0.0.1:$Port (HTTP $ollama). Start Ollama first."
    exit 1
}

Write-Host "Ollama is up. Opening reverse tunnel to $SshHost ..."
Write-Host "VPS 127.0.0.1:$Port  ->  this PC 127.0.0.1:$Port"
Write-Host "Leave this window open. Ctrl+C stops the tunnel."

ssh.exe -i $IdentityFile -o IdentitiesOnly=yes -N -T `
    -o BatchMode=yes `
    -o ExitOnForwardFailure=yes `
    -o ServerAliveInterval=30 `
    -o ServerAliveCountMax=3 `
    -R "127.0.0.1:${Port}:127.0.0.1:${Port}" `
    $SshHost
