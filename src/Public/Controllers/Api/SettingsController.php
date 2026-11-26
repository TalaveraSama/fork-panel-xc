<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class SettingsController extends BaseApiController
{
    private const SECTIONS = ['general', 'streaming', 'appearance', 'security', 'cluster'];

    public function index(ApiRequest $request): ApiResponse
    {
        $rows = Db::select('SELECT setting_key, setting_value, section FROM settings');
        $data = [];

        foreach ($rows as $row) {
            $data[$row['section']][$row['setting_key']] = $this->cast($row['setting_value']);
        }

        return ApiResponse::json(['data' => $data]);
    }

    public function general(ApiRequest $request): ApiResponse
    {
        return $this->sectionResponse('general');
    }

    public function updateGeneral(ApiRequest $request): ApiResponse
    {
        return $this->saveSection('general', $request);
    }

    public function streaming(ApiRequest $request): ApiResponse
    {
        return $this->sectionResponse('streaming');
    }

    public function updateStreaming(ApiRequest $request): ApiResponse
    {
        return $this->saveSection('streaming', $request);
    }

    public function appearance(ApiRequest $request): ApiResponse
    {
        return $this->sectionResponse('appearance');
    }

    public function updateAppearance(ApiRequest $request): ApiResponse
    {
        return $this->saveSection('appearance', $request);
    }

    public function security(ApiRequest $request): ApiResponse
    {
        return $this->sectionResponse('security');
    }

    public function updateSecurity(ApiRequest $request): ApiResponse
    {
        return $this->saveSection('security', $request);
    }

    public function cluster(ApiRequest $request): ApiResponse
    {
        return $this->sectionResponse('cluster');
    }

    public function updateCluster(ApiRequest $request): ApiResponse
    {
        return $this->saveSection('cluster', $request);
    }

    public function modules(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json(['data' => Db::select('SELECT * FROM modules ORDER BY name')]);
    }

    public function backup(ApiRequest $request): ApiResponse
    {
        $rows = Db::select('SELECT id, name, size, created_at FROM backups ORDER BY id DESC');

        return ApiResponse::json(['data' => $rows]);
    }

    public function createBackup(ApiRequest $request): ApiResponse
    {
        $name = 'backup-' . date('Ymd-His') . '.sql';

        $id = Db::insert(
            'INSERT INTO backups (name, status, created_at) VALUES (?, ?, ?)',
            [$name, 'queued', time()]
        );

        // Actual dump is performed by the panel's cron:backups job.
        return ApiResponse::json(['data' => ['id' => $id, 'name' => $name, 'status' => 'queued']], 201);
    }

    public function logs(ApiRequest $request): ApiResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 100)));
        $where = ['1 = 1'];
        $params = [];

        $level = $request->query('level');
        if ($level !== null && $level !== '') {
            $where[] = 'type = ?';
            $params[] = $level;
        }

        $rows = Db::select(
            sprintf('SELECT id, type, log_message, file, line, time FROM panel_logs WHERE %s ORDER BY id DESC LIMIT %d',
                implode(' AND ', $where), $limit),
            $params
        );

        return ApiResponse::json(['data' => $rows]);
    }

    private function sectionResponse(string $section): ApiResponse
    {
        $rows = Db::select('SELECT setting_key, setting_value FROM settings WHERE section = ?', [$section]);
        $data = [];

        foreach ($rows as $row) {
            $data[$row['setting_key']] = $this->cast($row['setting_value']);
        }

        return ApiResponse::json(['data' => $data]);
    }

    private function saveSection(string $section, ApiRequest $request): ApiResponse
    {
        $values = $request->json();

        if ($values === []) {
            return ApiResponse::error('No settings supplied', 422);
        }

        foreach ($values as $key => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', (string) $key)) {
                continue;
            }

            Db::execute(
                'INSERT INTO settings (section, setting_key, setting_value, updated_at) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
                [$section, (string) $key, is_array($value) ? json_encode($value) : (string) $value, time()]
            );
        }

        return $this->sectionResponse($section);
    }

    private function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === 'true' || $value === '1') {
            return true;
        }

        if ($value === 'false' || $value === '0') {
            return false;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
