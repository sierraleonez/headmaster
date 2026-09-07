import type { SVGAttributes } from 'react';

/** Three stacked bars: a board seen edge on. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" {...props}>
            <rect x="1" y="2" width="4" height="12" rx="1" />
            <rect x="6" y="2" width="4" height="8" rx="1" />
            <rect x="11" y="2" width="4" height="5" rx="1" />
        </svg>
    );
}
