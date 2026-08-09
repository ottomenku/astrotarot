<?php

$defaultPython = PHP_OS_FAMILY === 'Windows'
    ? base_path('.venv/Scripts/python.exe')
    : base_path('.venv/bin/python');

return [
    'python_bin' => env('EPHEMERIS_PYTHON_BIN', $defaultPython),
    'script' => base_path('python/ephemeris_calc.py'),
    'timeout' => 30,
    'default_lat' => 47.4979,
    'default_lon' => 19.0402,
    'conjunction_orb' => 1.0,
];
