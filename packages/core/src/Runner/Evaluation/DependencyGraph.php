<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

use Alama\Arazzo\Spec\Step;

class DependencyGraph
{
    /** @var array<string, Step> */
    private array $stepsById = [];

    /** @var string[] */
    private array $topologicalOrder = [];

    /** @var string[]|null */
    private ?array $cycle = null;

    /** @var array<string, string[]> */
    private array $unresolvedReferences = [];

    /** @var array<string, list<string>> explicit dependsOn + implicit output-reference deps */
    private array $effectiveDependencies = [];

    /**
     * @param  Step[]  $steps
     */
    public function __construct(array $steps)
    {
        foreach ($steps as $step) {
            $this->stepsById[$step->stepId] = $step;
        }

        $this->analyze();
    }

    private function analyze(): void
    {
        foreach ($this->stepsById as $id => $step) {
            $deps = $step->dependsOn;
            foreach (ImplicitDependencies::fromStep($step) as $implicit) {
                if (!in_array($implicit, $deps, true)) {
                    $deps[] = $implicit;
                }
            }
            $this->effectiveDependencies[$id] = $deps;
        }

        /** @var array<string,int> $color 0=white,1=grey,2=black */
        $color = [];
        foreach (array_keys($this->stepsById) as $id) {
            $color[$id] = 0;
        }

        $path = [];
        $reported = false;

        $dfs = function (string $node) use (&$dfs, &$color, &$path, &$reported): void {
            if ($reported) {
                return;
            }

            if (!isset($this->stepsById[$node])) {
                return;
            }

            $color[$node] = 1;
            $path[] = $node;

            $step = $this->stepsById[$node];
            foreach ($this->effectiveDependencies[$node] ?? [] as $next) {
                if (!isset($this->stepsById[$next])) {
                    $this->unresolvedReferences[$node][] = $next;

                    continue;
                }

                if ($color[$next] === 1) {
                    $cycleIndex = array_search($next, $path, true);
                    if ($cycleIndex !== false) {
                        $this->cycle = array_slice($path, (int) $cycleIndex);
                        $this->cycle[] = $next;
                    }
                    $reported = true;

                    return;
                }

                if ($color[$next] === 0) {
                    $dfs($next);
                }

                /** @phpstan-ignore if.alwaysFalse */
                if ($reported) {
                    return;
                }
            }

            $color[$node] = 2;
            array_pop($path);
            $this->topologicalOrder[] = $node;
        };

        foreach (array_keys($this->stepsById) as $id) {
            if ($color[$id] === 0) {
                $dfs($id);
            }
            if ($reported) {
                break;
            }
        }

    }

    /**
     * @return string[]
     */
    public function getTopologicalOrder(): array
    {
        return $this->topologicalOrder;
    }

    /**
     * @return string[]|null
     */
    public function getCycle(): ?array
    {
        return $this->cycle;
    }

    /**
     * @return array<string, string[]>
     */
    public function getUnresolvedReferences(): array
    {
        return $this->unresolvedReferences;
    }

    /**
     * Explicit dependsOn merged with implicit output-reference dependencies.
     *
     * @return list<string>
     */
    public function getEffectiveDependencies(string $stepId): array
    {
        return $this->effectiveDependencies[$stepId] ?? [];
    }

    /**
     * @return array<string, Step>
     */
    public function getStepsById(): array
    {
        return $this->stepsById;
    }
}
