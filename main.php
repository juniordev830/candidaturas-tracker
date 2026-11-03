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
function expect(bool $ok): void { if (!$ok) throw new RuntimeException('Teste falhou'); }
try {
    if (($argv[1]??'')==='--self-test') {
        $a=['id'=>'vaga-1','etapa'=>'enviada','curriculo'=>'cv-v2.pdf'];
        $b=transition($a,'triagem','2026-01-01'); expect($b['curriculo']==='cv-v2.pdf' && count($b['historico'])===1 && $a['etapa']==='enviada');
        try { transition($a,'contratada','2026-01-01'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        try { transition($a,'triagem','2026-02-30'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        echo "3 cenários OK\n"; exit(0);
    }
    if (count($argv)!==5) throw new InvalidArgumentException('Uso: php main.php candidatura.json NOVA_ETAPA DATA saida.json');
    $text=file_get_contents($argv[1]); if ($text===false) throw new RuntimeException('Falha de leitura');
    $data=json_decode($text,true,128,JSON_THROW_ON_ERROR); if (!is_array($data)) throw new InvalidArgumentException('Esperado objeto');
    $out=json_encode(transition($data,$argv[2],$argv[3]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    $file=fopen($argv[4],'x'); if ($file===false) throw new RuntimeException('Saída já existe ou não pode ser criada');
    if (fwrite($file,$out)!==strlen($out)) throw new RuntimeException('Falha de gravação'); fclose($file);
    echo "Novo arquivo gerado; entrada preservada.\n";
} catch(Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
