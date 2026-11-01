<?php

namespace App\Modules\Backup\Http\Requests;

use App\Modules\Backup\Services\BackupRules;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportBackupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:'.Contract::BACKUP_MAX_KB],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function backup(BackupRules $rules): array
    {
        $data = json_decode((string) file_get_contents($this->file('file')->getRealPath()), true);

        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'This file isn’t a Focus backup.']);
        }

        $validator = Validator::make($data, $rules->rules(), ['format.in' => 'This file isn’t a Focus backup.']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['file' => $validator->errors()->first()]);
        }

        return $validator->validated();
    }
}
