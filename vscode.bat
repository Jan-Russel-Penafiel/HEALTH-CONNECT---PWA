@echo off
setlocal enabledelayedexpansion

:: ─────────────────────────────────────────────────────────────
::  PHP Dev Launcher – opens VS Code + PHP‑server + PUBLIC tunnel
:: ─────────────────────────────────────────────────────────────
::  Change any paths / names below as you wish
::  Requires: "code" CLI in PATH (VS Code adds it via Command Palette
::            ⇢  View → Command Palette → “Shell Command: Install ‘code’ command”)
:: ─────────────────────────────────────────────────────────────

:: 1⃣  Always make tunnels PUBLIC (anonymous)
set DEV_TUNNELS_DEFAULT_VISIBILITY=public

:: 2⃣  Project list
set "project1=HEALTH CONNECT - Kakadien"
set "path1=C:\xampp\htdocs\connect"

set "project2=AID TRACK - Jerome"
set "path2=C:\xampp\htdocs\jer_new"

set "project3=IMMUCARE - Michael"
set "path3=C:\xampp\htdocs\mic_new"

set "project4=BSCAPS - Muhaimin"
set "path4=C:\xampp\htdocs\pablo"

set "project5=IMMUCARE"
set "path5=C:\xampp\htdocs\mic_new"

:: 3⃣  Text menu
echo.
echo Select a project to serve:
echo  1. %project1%
echo  2. %project2%
echo  3. %project3%
echo  4. %project4%
echo  5. %project5%
set /p choice=Enter number^> 

if "%choice%"=="1" (set "folder=%path1%" & set "pname=%project1%")
if "%choice%"=="2" (set "folder=%path2%" & set "pname=%project2%")
if "%choice%"=="3" (set "folder=%path3%" & set "pname=%project3%")
if "%choice%"=="4" (set "folder=%path4%" & set "pname=%project4%")
if "%choice%"=="5" (set "folder=%path5%" & set "pname=%project5%")

if not defined folder (
  echo Invalid choice.
  pause
  exit /b
)

echo.
echo -----------------------------------------------------------
echo  Launching  !pname!
echo -----------------------------------------------------------

:: 4⃣  Open VS Code on the folder (new window)
/*
   -n  = New window (omit if you prefer re‑using the last window)
   -r  = Re‑use window (choose one or the other)
*/
start "" code -n "!folder!"

:: 5⃣  PHP dev‑server in its own terminal
start "" cmd /k "cd /d \"!folder!\" && php -S localhost:8080"

:: 6⃣  Wait a moment so the server is listening
timeout /t 2 >nul

:: 7⃣  Dev Tunnel (always public) in another terminal
start "" cmd /k "code tunnel --port 8080 --public"

echo.
echo The public HTTPS URL will appear in the Dev Tunnel window.
echo Share that link to test on mobile or with teammates.
echo (Close the tunnel window or hit Ctrl+C to stop sharing.)
echo -----------------------------------------------------------
pause
