@extends('layouts.app')

@section('title', 'Шаблон КП')

@section('top')
    <div>
        <h1>Шаблон КП</h1>
        <p class="sub">Поля в фигурных скобках подставляются сами</p>
    </div>
    <div class="segmented" role="group" aria-label="Вид">
        <button type="button" data-view="edit" aria-pressed="true">Текст</button>
        <button type="button" data-view="preview" aria-pressed="false">Как увидит получатель</button>
    </div>
@endsection

@section('content')
    <div data-panel="edit" style="display:flex;flex-direction:column;gap:10px">
        @if ($missingAttachment)
            <div class="flash err" role="alert">В тексте написано «во вложении», а PDF с КП не загружен. Загрузите файл ниже или уберите эту строку.</div>
        @endif

        <form method="post" action="{{ route('template.update') }}" enctype="multipart/form-data" class="card">
            @csrf
            @method('put')
            <label>Тема письма
                <input type="text" name="subject" value="{{ old('subject', $subject) }}" maxlength="200" required>
            </label>
            @error('subject') <p class="error">{{ $message }}</p> @enderror

            <label>Текст
                <textarea name="body" rows="16" maxlength="10000" required>{{ old('body', $body) }}</textarea>
            </label>
            @error('body') <p class="error">{{ $message }}</p> @enderror
            <p class="muted">В конце каждого письма автоматически добавляется: «{{ \App\Services\KpTemplateService::UNSUBSCRIBE }}»</p>

            <label>Вложение — PDF с подробным КП
                <input type="file" name="attachment" accept="application/pdf">
            </label>
            <p class="muted">
                @if ($hasAttachment)
                    Загружен, в письме называется «{{ $attachmentName }}». Новый файл заменит его.
                @else
                    Не загружен. До 10 МБ.
                @endif
            </p>
            @error('attachment') <p class="error">{{ $message }}</p> @enderror
            @if ($hasAttachment)
                <label class="check"><input type="checkbox" name="remove_attachment" value="1"> Убрать вложение</label>
            @endif

            <label>Не больше писем в день
                <input type="text" name="daily_limit" value="{{ old('daily_limit', $dailyLimit) }}" inputmode="numeric" required>
            </label>
            <p class="muted">Чтобы почтовые службы не сочли письма рассылкой.</p>
            @error('daily_limit') <p class="error">{{ $message }}</p> @enderror

            <button type="submit">Сохранить шаблон</button>
        </form>

        <section class="card">
            <h2 class="section-title">Что подставляется</h2>
            <div class="tags">
                @foreach ($fields as $field)
                    <span class="tag">{{ $field }}</span>
                @endforeach
            </div>
            <p class="muted">Каждое письмо получается личным: в нём конкретный объект и контракт.</p>
        </section>
    </div>

    <div data-panel="preview" hidden>
        @if ($preview && $sample)
            <section class="card">
                <div class="muted" style="display:flex;flex-direction:column;gap:4px;padding-bottom:10px;border-bottom:1px solid var(--chip);font-size:12px">
                    <span>Кому: {{ $sample->company->name }} · {{ $sample->company->email ?? 'почта не найдена' }}</span>
                    <span>Вложение: {{ $hasAttachment ? $attachmentName : 'нет' }}</span>
                    <b style="font-size:14px;color:var(--ink)">{{ $preview->subject }}</b>
                </div>
                <p class="letter">{{ $preview->body }}</p>
            </section>
            <p class="muted">Пример на самом свежем тендере. Показан сохранённый текст.</p>
        @else
            <div class="card empty">
                <p class="muted">Нет ни одного тендера для примера. Загрузите демо-тендеры в «Настройках».</p>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-view]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            document.querySelectorAll('[data-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.panel !== button.dataset.view;
            });
        });
    });
</script>
@endpush
