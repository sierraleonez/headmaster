import { Head } from '@inertiajs/react';

import { Board } from '@/components/board/board';
import { LogView } from '@/components/log/log-view';
import { ProjectHeader } from '@/components/project-header';
import type { ProjectPayload, TrailStep } from '@/types';

export default function ProjectPage({
    project,
    trail,
}: {
    project: ProjectPayload;
    trail: TrailStep[];
}) {
    return (
        <>
            <Head title={project.name} />

            <div className="flex h-full min-h-0 flex-col">
                <ProjectHeader project={project} trail={trail} />

                {project.kind === 'board' ? (
                    <Board project={project} />
                ) : (
                    <LogView project={project} />
                )}
            </div>
        </>
    );
}
