<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ParticipantStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipantStat>
 */
class ParticipantStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipantStat::class);
    }

    /**
     * Get the maximum values (total and average) for all stats definitions.
     */
    public function getLeagueMaxStats(string $season): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT sd.code, MAX(ps.total_value) as max_tot, MAX(ps.average_value) as max_avg 
            FROM participant_stats ps 
            JOIN stat_definitions sd ON ps.stat_definition_id = sd.id 
            JOIN participants p ON ps.participant_id = p.id
            WHERE ps.season = :season AND p.external_id != 'clifford_benchman'
            GROUP BY sd.code
        ";

        $result = $conn->executeQuery($sql, ['season' => $season])->fetchAllAssociative();
        $maxStats = [];
        foreach ($result as $row) {
            $maxStats[$row['code']] = [
                'total' => (float) $row['max_tot'],
                'average' => (float) $row['max_avg']
            ];
        }

        return $maxStats;
    }

    /**
     * Get turnover metrics per 10 minutes (best/min and worst/max rates).
     */
    public function getTurnoverMetrics(string $season): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT 
                MIN(to_rate) as best_rate, 
                MAX(to_rate) as worst_rate
            FROM (
                SELECT (ps_to.total_value / NULLIF(ps_min.total_value, 0)) * 10 as to_rate
                FROM participant_stats ps_to
                JOIN participant_stats ps_min ON ps_to.participant_id = ps_min.participant_id AND ps_to.season = ps_min.season
                JOIN stat_definitions sd_to ON ps_to.stat_definition_id = sd_to.id AND sd_to.code = 'turnovers'
                JOIN stat_definitions sd_min ON ps_min.stat_definition_id = sd_min.id AND sd_min.code = 'minutes_played'
                JOIN participants p ON ps_to.participant_id = p.id
                WHERE ps_to.season = :season 
                  AND p.external_id != 'clifford_benchman'
                  AND ps_min.total_value >= 1000
            ) rates
        ";

        $result = $conn->executeQuery($sql, ['season' => $season])->fetchAssociative();
        
        return [
            'best' => (float) ($result['best_rate'] ?? 0),
            'worst' => (float) ($result['worst_rate'] ?? 1)
        ];
    }
}
