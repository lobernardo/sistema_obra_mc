# Decisões do desenvolvedor sobre as questões abertas do PLAN.md (2026-09-22)

- Teste de concorrência do convite: simulação no mesmo processo (10 instâncias desatualizadas), sem nova suíte nem mudança no phpunit.xml.
- Consulta de colisão de nomes de obra em produção (somente leitura, antes do merge da Fase 1): executada pelo Claude via Railway, com confirmação do desenvolvedor no momento.
- Token do convite (DECISÃO FINAL, imutável na implementação): token é segredo; token em claro não aparece em logs de aplicação nem access logs. Codificado no SPEC v1.2 como RF-38/NC-08 (token só no fragmento `#`, lookup por POST Livewire, nunca em path/query/sessão/snapshot/logs/auditoria).
