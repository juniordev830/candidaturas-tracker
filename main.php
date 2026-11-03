<?php
declare(strict_types=1);
const STEPS=['enviada'=>['triagem','rejeitada','retirada'],'triagem'=>['entrevista','rejeitada','retirada'],'entrevista'=>['proposta','rejeitada','retirada'],'proposta'=>['contratada','retirada'],'contratada'=>[],'rejeitada'=>[],'retirada'=>[]];
function transition(array $record,string $next,string $date): array {
    if (!isset(STEPS[$record['etapa']??'']) || !in_array($next,STEPS[$record['etapa']],true)) throw new InvalidArgumentException('Transição de etapa inválida');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Data inválida');
    $history=$record['historico']??[];
    if ($history && $date<$history[count($history)-1]['data']) throw new InvalidArgumentException('Data anterior ao último evento');
    $history[]=['de'=>$record['etapa'],'para'=>$next,'data'=>$date];
    $record['etapa']=$next; $record['historico']=$history; return $record;
}
/**
 * Read-only follow-up report for a single application. An initial application
 * without history has an unknown age and is never marked overdue.
 */
function applicationSummary(array $record, string $asOf, int $followUpDays=14): array {
    if ($followUpDays < 1 || $followUpDays > 3650)
        throw new InvalidArgumentException('Limite deve estar entre 1 e 3650 dias');
    $asOfDate=DateTimeImmutable::createFromFormat('!Y-m-d', $asOf);
    if (!$asOfDate || $asOfDate->format('Y-m-d')!==$asOf)
        throw new InvalidArgumentException('Data de consulta inválida');
    $stage=$record['etapa']??null;
    if (!is_string($stage) || !array_key_exists($stage, STEPS))
        throw new InvalidArgumentException('Etapa atual inválida');
    $history=$record['historico']??[];
    if (!is_array($history) || !array_is_list($history) || count($history)>10000)
        throw new InvalidArgumentException('Histórico inválido');

    $previousTo=null;
    $previousDate=null;
    $last=null;
    foreach ($history as $event) {
        if (!is_array($event) || !is_string($event['de']??null) ||
            !is_string($event['para']??null) || !is_string($event['data']??null))
            throw new InvalidArgumentException('Evento do histórico inválido');
        if (!isset(STEPS[$event['de']]) ||
            !in_array($event['para'], STEPS[$event['de']], true) ||
            ($previousTo !== null && $previousTo !== $event['de']))
            throw new InvalidArgumentException('Sequência de etapas inválida');
        $eventDate=DateTimeImmutable::createFromFormat('!Y-m-d', $event['data']);
        if (!$eventDate || $eventDate->format('Y-m-d')!==$event['data'] ||
            ($previousDate !== null && $eventDate < $previousDate))
            throw new InvalidArgumentException('Sequência de datas inválida');
        $previousTo=$event['para'];
        $previousDate=$eventDate;
        $last=$event['data'];
    }
    if ($previousTo !== null && $previousTo !== $stage)
        throw new InvalidArgumentException('Etapa atual divergente do histórico');
    if ($previousDate !== null && $asOfDate < $previousDate)
        throw new InvalidArgumentException('Consulta anterior à última movimentação');
    $days=$previousDate === null ? null : (int)$previousDate->diff($asOfDate)->format('%a');
    $terminal=in_array($stage, ['contratada','rejeitada','retirada'], true);
    return [
        'id'=>is_string($record['id']??null) ? $record['id'] : null,
        'etapa'=>$stage,
        'eventos'=>count($history),
        'ultima_movimentacao'=>$last,
        'dias_na_etapa'=>$days,
        'acompanhar'=>$days !== null && $days >= $followUpDays && !$terminal,
    ];
}

function expect(bool $ok): void { if (!$ok) throw new RuntimeException('Teste falhou'); }
try {
    if (($argv[1]??'')==='--self-test') {
        $a=['id'=>'vaga-1','etapa'=>'enviada','curriculo'=>'cv-v2.pdf'];
        $b=transition($a,'triagem','2026-01-01'); expect($b['curriculo']==='cv-v2.pdf' && count($b['historico'])===1 && $a['etapa']==='enviada');
        try { transition($a,'contratada','2026-01-01'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        try { transition($a,'triagem','2026-02-30'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        $old=['id'=>'vaga-1','etapa'=>'enviada','historico'=>[]];
        $unknown=applicationSummary($old,'2026-11-03');
        expect($unknown['dias_na_etapa']===null && !$unknown['acompanhar']);
        $updated=transition($old,'triagem','2026-10-01');
        $report=applicationSummary($updated,'2026-11-03',14);
        expect($report['dias_na_etapa']===33 && $report['acompanhar'] && $report['eventos']===1);
        $closed=transition($updated,'rejeitada','2026-10-02');
        expect(!applicationSummary($closed,'2026-11-03')['acompanhar']);
        try { applicationSummary($updated,'2026-09-30'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        try { applicationSummary($updated,'2026-11-03',0); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        try { applicationSummary(['etapa'=>'triagem','historico'=>[['de'=>'enviada','para'=>'entrevista','data'=>'2026-10-01']]],'2026-11-03'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        echo "9 cenários OK\n"; exit(0);
    }
    if (($argv[1]??'')==='--resumo') {
        if (count($argv)!==4 && count($argv)!==5)
            throw new InvalidArgumentException('Uso: php main.php --resumo candidatura.json DATA [LIMITE_DIAS]');
        if (isset($argv[4]) && (!ctype_digit($argv[4]) || (int)$argv[4]<1 || (int)$argv[4]>3650))
            throw new InvalidArgumentException('Limite deve estar entre 1 e 3650 dias');
        if (!is_file($argv[2]) || filesize($argv[2])>2_000_000)
            throw new InvalidArgumentException('Arquivo ausente ou maior que 2 MB');
        $text=file_get_contents($argv[2]);
        if ($text===false) throw new RuntimeException('Falha de leitura');
        $record=json_decode($text,true,128,JSON_THROW_ON_ERROR);
        if (!is_array($record) || array_is_list($record))
            throw new InvalidArgumentException('Esperado objeto JSON de candidatura');
        echo json_encode(applicationSummary($record,$argv[3],(int)($argv[4]??14)),
            JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    if (count($argv)!==5) throw new InvalidArgumentException('Uso: php main.php candidatura.json NOVA_ETAPA DATA saida.json');
    $text=file_get_contents($argv[1]); if ($text===false) throw new RuntimeException('Falha de leitura');
    $data=json_decode($text,true,128,JSON_THROW_ON_ERROR); if (!is_array($data)) throw new InvalidArgumentException('Esperado objeto');
    $out=json_encode(transition($data,$argv[2],$argv[3]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    $file=fopen($argv[4],'x'); if ($file===false) throw new RuntimeException('Saída já existe ou não pode ser criada');
    if (fwrite($file,$out)!==strlen($out)) throw new RuntimeException('Falha de gravação'); fclose($file);
    echo "Novo arquivo gerado; entrada preservada.\n";
} catch(Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
