import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { useMemo } from 'react';
import { Bar } from 'react-chartjs-2';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { AnalysisSkill } from '@/types/analysis';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const palette = {
    light: {
        bar: '#3b6ea5',
        grid: '#e7e7e7',
        tick: '#8e8e8e',
        tooltipBackground: '#252525',
        tooltipText: '#fafafa',
    },
    dark: {
        bar: '#7aa9d9',
        grid: '#333333',
        tick: '#b4b4b4',
        tooltipBackground: '#fafafa',
        tooltipText: '#252525',
    },
} as const;

const prefersReducedMotion = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

/**
 * Horizontal bars beat a radar here: with three to five weighted criteria a
 * radar is decorative, while bars let a reader rank the skills at a glance.
 */
export function SkillChart({
    skills,
    className,
}: {
    skills: AnalysisSkill[];
    className?: string;
}) {
    const { resolvedAppearance } = useAppearance();
    const colors = palette[resolvedAppearance];

    const data = useMemo<ChartData<'bar'>>(
        () => ({
            labels: skills.map((skill) => skill.name),
            datasets: [
                {
                    label: 'Score',
                    data: skills.map((skill) => skill.score),
                    backgroundColor: colors.bar,
                    hoverBackgroundColor: colors.bar,
                    borderRadius: 4,
                    borderSkipped: false,
                    barThickness: 18,
                },
            ],
        }),
        [skills, colors.bar],
    );

    const options = useMemo<ChartOptions<'bar'>>(
        () => ({
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: prefersReducedMotion() ? false : { duration: 400 },
            layout: { padding: { right: 4 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: colors.tooltipBackground,
                    titleColor: colors.tooltipText,
                    bodyColor: colors.tooltipText,
                    displayColors: false,
                    padding: 8,
                    callbacks: {
                        label: (context) => `${context.parsed.x} out of 100`,
                    },
                },
            },
            scales: {
                x: {
                    min: 0,
                    max: 100,
                    border: { display: false },
                    grid: { color: colors.grid },
                    ticks: {
                        color: colors.tick,
                        stepSize: 25,
                        font: { size: 11 },
                    },
                },
                y: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        color: colors.tick,
                        font: { size: 12 },
                        crossAlign: 'far',
                    },
                },
            },
        }),
        [colors],
    );

    if (skills.length === 0) {
        return (
            <p className={cn('text-sm text-muted-foreground', className)}>
                No skills have been scored for this candidate yet.
            </p>
        );
    }

    return (
        <div className={className}>
            <div
                aria-hidden="true"
                style={{ height: skills.length * 52 + 32 }}
                className="w-full"
            >
                <Bar data={data} options={options} />
            </div>

            <ul className="sr-only">
                {skills.map((skill) => (
                    <li key={skill.name}>
                        {skill.name}: {skill.score} out of 100, worth{' '}
                        {skill.weight}% of the rubric.
                    </li>
                ))}
            </ul>
        </div>
    );
}
