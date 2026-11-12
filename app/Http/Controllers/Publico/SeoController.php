<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\ChamadaProposta;
use App\Models\ChamadaVoluntario;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** robots.txt e sitemap.xml: ajudam buscadores a achar eventos, chamadas e trabalhos publicados e a ignorar as áreas privadas. */
class SeoController extends Controller
{
    public function robots()
    {
        $linhas = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /gestao',
            'Disallow: /avaliador',
            'Disallow: /minha-area',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /frequencia',
            'Disallow: /certificado',
            'Allow: /',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
        ];

        return response(implode("\n", $linhas) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap()
    {
        $xml = Cache::remember('sitemap-xml', 3600, function () {
            $urls = [['loc' => url('/'), 'prio' => '1.0', 'freq' => 'daily']];
            $urls[] = ['loc' => route('privacidade'), 'prio' => '0.3', 'freq' => 'yearly'];

            // Eventos criados por contas fictícias de teste não entram (mesma regra do portal)
            $eventos = Evento::whereNotIn('criado_por', User::where('email', 'like', '%@teste.com')->select('id'))
                ->with(['trabalhoConfig', 'anais'])->orderByDesc('data_inicio')->take(500)->get();

            foreach ($eventos as $e) {
                $urls[] = ['loc' => route('evento.publico.show', $e->slug), 'mod' => $e->updated_at, 'prio' => '0.9', 'freq' => 'weekly'];
                if ($e->anais && $e->anais->status === 'publicado') {
                    $urls[] = ['loc' => route('anais.index', $e->slug), 'mod' => $e->anais->updated_at, 'prio' => '0.6', 'freq' => 'monthly'];
                }
                if ($e->trabalhoConfig?->estaAberta()) {
                    $urls[] = ['loc' => route('trabalhos.create', $e->slug), 'mod' => $e->trabalhoConfig->updated_at, 'prio' => '0.7', 'freq' => 'weekly'];
                }
            }
            foreach (ChamadaProposta::abertas()->whereIn('evento_id', $eventos->pluck('id'))->get() as $c) {
                $urls[] = ['loc' => route('propostas.create', $c), 'mod' => $c->updated_at, 'prio' => '0.7', 'freq' => 'weekly'];
            }
            foreach (ChamadaVoluntario::abertas()->whereIn('evento_id', $eventos->pluck('id'))->get() as $c) {
                $urls[] = ['loc' => route('voluntariado.create', $c), 'mod' => $c->updated_at, 'prio' => '0.7', 'freq' => 'weekly'];
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($urls as $u) {
                $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>'
                    . (isset($u['mod']) && $u['mod'] ? '<lastmod>' . $u['mod']->toAtomString() . '</lastmod>' : '')
                    . '<changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority></url>' . "\n";
            }

            return $xml . '</urlset>' . "\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
