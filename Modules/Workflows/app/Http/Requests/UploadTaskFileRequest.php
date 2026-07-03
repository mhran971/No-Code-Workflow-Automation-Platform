<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTaskFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,gif,svg,webp,bmp,tiff,txt,zip,rar', 'max:20480'],
        ];
    }
}
