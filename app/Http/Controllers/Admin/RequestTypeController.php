<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestQuestion;
use App\Models\RequestType;
use App\Models\TaskTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequestTypeController extends Controller
{
    public function index()
    {
        return view('admin.request-types.index', [
            'requestTypes' => RequestType::with('questions.options')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.request-types.form', [
            'requestType' => new RequestType(),
            'mode' => 'create',
            'taskTitles' => TaskTitle::where('is_active', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $request) {
            $requestType = RequestType::create([
                'name' => $validated['name'],
                'role_scope' => $validated['role_scope'],
                'urgency_enabled' => $request->boolean('urgency_enabled'),
                'attachment_required' => $request->boolean('attachment_required'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncQuestions($requestType, $validated['questions']);
        });

        return redirect()->route('admin.request-types.index')->with('success', 'Request type created successfully.');
    }

    public function show(RequestType $requestType)
    {
        return view('admin.request-types.show', ['requestType' => $requestType->load('questions.options')]);
    }

    public function edit(RequestType $requestType)
    {
        return view('admin.request-types.form', [
            'requestType' => $requestType->load('questions.options'),
            'mode' => 'edit',
            'taskTitles' => TaskTitle::where('is_active', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, RequestType $requestType)
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $request, $requestType) {
            $requestType->update([
                'name' => $validated['name'],
                'role_scope' => $validated['role_scope'],
                'urgency_enabled' => $request->boolean('urgency_enabled'),
                'attachment_required' => $request->boolean('attachment_required'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $requestType->questions()->delete();
            $this->syncQuestions($requestType, $validated['questions']);
        });

        return redirect()->route('admin.request-types.index')->with('success', 'Request type updated successfully.');
    }

    public function destroy(RequestType $requestType)
    {
        $requestType->delete();

        return redirect()->route('admin.request-types.index')->with('success', 'Request type deleted successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_scope' => ['required', Rule::in(['hq_staff', 'kindergarten', 'ssu', 'all'])],
            'urgency_enabled' => ['nullable', 'boolean'],
            'attachment_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:5000'],
            'questions.*.question_type' => ['required', Rule::in(['remark', 'radio', 'date_range', 'checkbox', 'task_title'])],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.start_label' => ['nullable', 'string', 'max:255'],
            'questions.*.end_label' => ['nullable', 'string', 'max:255'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.option_text' => ['nullable', 'string', 'max:255'],
            'questions.*.options.*.allows_other_text' => ['nullable', 'boolean'],
        ]);
    }

    private function syncQuestions(RequestType $requestType, array $questions): void
    {
        foreach (array_values($questions) as $questionIndex => $questionData) {
            $question = $requestType->questions()->create([
                'question_text' => $questionData['question_text'],
                'question_type' => $questionData['question_type'],
                'sort_order' => $questionIndex + 1,
                'is_required' => !empty($questionData['is_required']),
                'start_label' => $questionData['start_label'] ?? null,
                'end_label' => $questionData['end_label'] ?? null,
            ]);

            if (in_array($questionData['question_type'], [RequestQuestion::TYPE_RADIO, RequestQuestion::TYPE_CHECKBOX], true)) {
                $options = collect($questionData['options'] ?? [])
                    ->filter(fn ($option) => filled($option['option_text'] ?? null))
                    ->values();

                foreach ($options as $optionIndex => $optionData) {
                    $question->options()->create([
                        'option_text' => $optionData['option_text'],
                        'sort_order' => $optionIndex + 1,
                        'allows_other_text' => $questionData['question_type'] === RequestQuestion::TYPE_RADIO && !empty($optionData['allows_other_text']),
                    ]);
                }
            }
        }
    }
}
