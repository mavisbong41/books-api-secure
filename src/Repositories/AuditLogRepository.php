<?php
namespace App\Repositories;

use PDO;

final class AuditLogRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(
        int $actorId,
        string $action,
        ?string $target = null,
        ?string $ip = null,
        ?string $detail = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (actor_id, action, target, ip_address, detail)
             VALUES (:actor_id, :action, :target, :ip_address, :detail)'
        );

        $stmt->execute([
            ':actor_id' => $actorId,
            ':action'   => $action,
            ':target'   => $target,
            ':ip_address' => $ip,
            ':detail'   => $detail
        ]);
    }
}