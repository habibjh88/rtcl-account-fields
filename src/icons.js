/**
 * Small inline SVG icons (no external icon dependency).
 */

const svg = ( children, extra = {} ) => (
	<svg
		width="20"
		height="20"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.8"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		{ ...extra }
	>
		{ children }
	</svg>
);

export const IconText = () => svg( <><path d="M4 7V5h16v2" /><path d="M9 19h6" /><path d="M12 5v14" /></> );
export const IconTextarea = () => svg( <><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M7 9h10M7 13h10M7 17h6" /></> );
export const IconNumber = () => svg( <><path d="M9 4L7 20M17 4l-2 16M4 9h16M3 15h16" /></> );
export const IconEmail = () => svg( <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></> );
export const IconUrl = () => svg( <><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></> );
export const IconPhone = () => svg( <><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L20 13l1 4v3a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z" /></> );
export const IconSelect = () => svg( <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m8 11 3 3 3-3" /></> );
export const IconCheckbox = () => svg( <><rect x="4" y="4" width="16" height="16" rx="3" /><path d="m8 12 3 3 5-6" /></> );
export const IconRadio = () => svg( <><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none" /></> );
export const IconFile = () => svg( <><path d="M14 3v5h5" /><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z" /><path d="M12 17v-5M9.5 14.5 12 12l2.5 2.5" /></> );

export const IconGrip = () => svg(
	<><circle cx="9" cy="6" r="1" /><circle cx="9" cy="12" r="1" /><circle cx="9" cy="18" r="1" /><circle cx="15" cy="6" r="1" /><circle cx="15" cy="12" r="1" /><circle cx="15" cy="18" r="1" /></>,
	{ fill: 'currentColor', stroke: 'none' }
);
export const IconTrash = () => svg( <><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3" /></> );
export const IconPlus = () => svg( <><path d="M12 5v14M5 12h14" /></> );
export const IconStar = () => svg( <><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.2l1-5.8L3.5 9.2l5.9-.9L12 3z" /></>, { fill: 'currentColor', stroke: 'none' } );
export const IconEye = () => svg( <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" /></> );
export const IconCheck = () => svg( <><path d="m5 12 5 5L20 7" /></> );
export const IconClose = () => svg( <><path d="M6 6l12 12M18 6L6 18" /></> );
export const IconCopy = () => svg( <><rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></> );

export const TYPE_ICONS = {
	text: IconText,
	textarea: IconTextarea,
	number: IconNumber,
	email: IconEmail,
	url: IconUrl,
	tel: IconPhone,
	select: IconSelect,
	radio: IconRadio,
	checkbox: IconCheckbox,
	file: IconFile,
};

export const TYPE_HINTS = {
	text: 'Single line of text',
	textarea: 'Multi-line paragraph',
	number: 'Numeric value',
	email: 'Email address',
	url: 'Website / link',
	tel: 'Phone number',
	select: 'Dropdown of options',
	radio: 'Single choice from a list',
	checkbox: 'One or more choices',
	file: 'File / image upload',
};
