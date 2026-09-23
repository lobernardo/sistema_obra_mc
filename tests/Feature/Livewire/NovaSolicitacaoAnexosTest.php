<?php

use App\Enums\PedidoAttachmentKind;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();
    Storage::fake('local');
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->requester = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->requester->obras()->attach($this->obra->id);
});

function novoAnexoPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, anexoPdfBytes());
}

/**
 * @return list<string>
 */
function listedAnexoNames(string $html): array
{
    preg_match_all('/<span data-anexo-name[^>]*>([^<]*)<\/span>/', $html, $matches);

    return array_map(fn (string $name): string => html_entity_decode(trim($name)), $matches[1]);
}

test('each uploaded file joins the list with a "Remover" control (UI-01)', function () {
    $component = Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)
        ->set('novoAnexo', novoAnexoPdf('a.pdf'))
        ->set('novoAnexo', UploadedFile::fake()->createWithContent('b.png', anexoPngBytes()))
        ->set('novoAnexo', novoAnexoPdf('c.pdf'))
        ->assertHasNoErrors()
        ->assertSet('novoAnexo', null)
        ->assertCount('anexos', 3)
        ->assertSee('Remover');

    expect(listedAnexoNames($component->html()))->toBe(['a.pdf', 'b.png', 'c.pdf']);
});

test('removerAnexo drops the file and submit stores only the remaining ones (RF-08)', function () {
    $component = Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)
        ->set('novoAnexo', novoAnexoPdf('a.pdf'))
        ->set('novoAnexo', novoAnexoPdf('b.pdf'))
        ->set('novoAnexo', novoAnexoPdf('c.pdf'))
        ->call('removerAnexo', 1)
        ->assertCount('anexos', 2);

    expect(listedAnexoNames($component->html()))->toBe(['a.pdf', 'c.pdf']);

    $component
        ->set('obra_selection', (string) $this->obra->id)
        ->set('descricao', 'Cimento')
        ->set('needed_at', '2026-10-01')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('anexos', []);

    $pedido = Pedido::query()->sole();
    expect($pedido->attachments()->pluck('original_name')->all())->toBe(['a.pdf', 'c.pdf']);
    expect($pedido->attachments()->pluck('kind')->all())->toBe([PedidoAttachmentKind::Anexo, PedidoAttachmentKind::Anexo]);
    expect(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toHaveCount(2);
});

test('an invalid file shows its error and is not appended (RF-14)', function () {
    Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)
        ->set('novoAnexo', UploadedFile::fake()->createWithContent('falso.pdf', anexoHtmlBytes()))
        ->assertHasErrors(['novoAnexo'])
        ->assertSee('O arquivo «falso.pdf» não é de um tipo permitido (JPG, PNG, WEBP, PDF, DOCX ou XLSX).')
        ->assertCount('anexos', 0)
        ->set('novoAnexo', novoAnexoPdf('ok.pdf'))
        ->assertHasNoErrors()
        ->assertCount('anexos', 1);
});

test('the 11th file is refused (RF-14)', function () {
    $component = Livewire::actingAs($this->requester)->test(NovaSolicitacao::class);

    foreach (range(1, 10) as $i) {
        $component->set('novoAnexo', novoAnexoPdf("anexo-{$i}.pdf"));
    }

    $component->assertHasNoErrors()->assertCount('anexos', 10)
        ->set('novoAnexo', novoAnexoPdf('anexo-11.pdf'))
        ->assertHasErrors(['novoAnexo'])
        ->assertSee('Envie no máximo 10 anexos.')
        ->assertCount('anexos', 10);
});

test('an invalid file forged into the list on submit is refused by the Action and nothing is written (RF-15)', function () {
    Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)
        ->set('novoAnexo', novoAnexoPdf('ok.pdf'))
        ->set('anexos.1', UploadedFile::fake()->createWithContent('falso.png', anexoHtmlBytes()))
        ->set('obra_selection', (string) $this->obra->id)
        ->set('descricao', 'Cimento')
        ->set('needed_at', '2026-10-01')
        ->call('submit')
        ->assertHasErrors(['anexos.1'])
        ->assertSee('«falso.png»');

    expect(Pedido::query()->count())->toBe(0);
    expect(PedidoAttachment::query()->count())->toBe(0);
    expect(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toBe([]);
});

test('the limits text is exact and the input uploads without wire:model (UI-01, RNF-07)', function () {
    $html = Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)->html();

    expect($html)->toContain('JPG, PNG, WEBP, PDF, DOCX ou XLSX; até 10 MB por arquivo; até 10 arquivos');
    expect($html)->toContain('accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx"');
    expect($html)->toContain("\$wire.upload('novoAnexo'");

    preg_match('/<input id="anexos"[^>]*>/', $html, $input);
    expect($input[0])->toContain('multiple');
    expect($input[0])->not->toContain('wire:model');
});

test('the rendered HTML never exposes a preview of a chosen file (RNF-01)', function () {
    $html = Livewire::actingAs($this->requester)->test(NovaSolicitacao::class)
        ->set('novoAnexo', UploadedFile::fake()->createWithContent('foto.png', anexoPngBytes()))
        ->assertCount('anexos', 1)
        ->html();

    expect($html)->not->toContain('livewire/preview-file');
    expect($html)->not->toContain('preview-file');
    expect($html)->not->toContain('<img');
});
