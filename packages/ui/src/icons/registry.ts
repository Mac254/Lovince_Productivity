/**
 * The one icon set: Solar (linear; bold only for filled states), compiled to inline SVG at build time.
 * Components refer to icons by meaning; swap a glyph or the whole set here only.
 */
import IconAdd from '~icons/solar/add-linear';
import IconAlertCircle from '~icons/solar/danger-circle-linear';
import IconArrowLeft from '~icons/solar/arrow-left-linear';
import IconArrowRight from '~icons/solar/arrow-right-linear';
import IconArrowUpRight from '~icons/solar/arrow-right-up-linear';
import IconBlocker from '~icons/solar/shield-warning-linear';
import IconCalendar from '~icons/solar/calendar-linear';
import IconCalendarCheck from '~icons/solar/calendar-mark-linear';
import IconCalendarClock from '~icons/solar/alarm-linear';
import IconCalendarRange from '~icons/solar/calendar-date-linear';
import IconCamera from '~icons/solar/camera-linear';
import IconCheck from '~icons/solar/check-linear';
import IconCheckCircle from '~icons/solar/check-circle-linear';
import IconChecklist from '~icons/solar/checklist-minimalistic-linear';
import IconChevronDown from '~icons/solar/alt-arrow-down-linear';
import IconChevronLeft from '~icons/solar/alt-arrow-left-linear';
import IconChevronRight from '~icons/solar/alt-arrow-right-linear';
import IconCircleDashed from '~icons/solar/circle-dashed-linear';
import IconCircleDot from '~icons/solar/play-circle-linear';
import IconClock from '~icons/solar/clock-circle-linear';
import IconClose from '~icons/solar/close-linear';
import IconCloseCircle from '~icons/solar/close-circle-linear';
import IconDashboard from '~icons/solar/widget-linear';
import IconEdit from '~icons/solar/pen-linear';
import IconExitFullscreen from '~icons/solar/minimize-square-linear';
import IconEye from '~icons/solar/eye-linear';
import IconFlame from '~icons/solar/fire-linear';
import IconForbidden from '~icons/solar/forbidden-circle-linear';
import IconFullscreen from '~icons/solar/maximize-square-linear';
import IconHistory from '~icons/solar/history-linear';
import IconHourglass from '~icons/solar/hourglass-linear';
import IconImage from '~icons/solar/gallery-linear';
import IconInfo from '~icons/solar/info-circle-linear';
import IconIntegration from '~icons/solar/branching-paths-up-linear';
import IconJournal from '~icons/solar/notebook-linear';
import IconKey from '~icons/solar/key-linear';
import IconLock from '~icons/solar/lock-keyhole-minimalistic-linear';
import IconLogout from '~icons/solar/logout-3-linear';
import IconMenu from '~icons/solar/hamburger-menu-linear';
import IconMetrics from '~icons/solar/chart-2-linear';
import IconMore from '~icons/solar/menu-dots-linear';
import IconOffline from '~icons/solar/cloud-cross-linear';
import IconPalette from '~icons/solar/palette-linear';
import IconQuote from '~icons/solar/quote-linear';
import IconReport from '~icons/solar/clipboard-list-linear';
import IconRetry from '~icons/solar/restart-linear';
import IconSave from '~icons/solar/diskette-linear';
import IconSearch from '~icons/solar/magnifer-linear';
import IconServerError from '~icons/solar/server-linear';
import IconShuffle from '~icons/solar/shuffle-linear';
import IconSpinner from '~icons/solar/refresh-linear';
import IconStar from '~icons/solar/star-linear';
import IconStarFilled from '~icons/solar/star-bold';
import IconSun from '~icons/solar/sun-linear';
import IconTag from '~icons/solar/tag-linear';
import IconTarget from '~icons/solar/target-linear';
import IconTasks from '~icons/solar/list-check-linear';
import IconTrash from '~icons/solar/trash-bin-minimalistic-linear';
import IconUndo from '~icons/solar/undo-left-round-linear';
import IconUpload from '~icons/solar/upload-linear';
import IconUser from '~icons/solar/user-circle-linear';
import IconWarning from '~icons/solar/danger-triangle-linear';

export const icons = {
    add: IconAdd,
    'alert-circle': IconAlertCircle,
    'arrow-left': IconArrowLeft,
    'arrow-right': IconArrowRight,
    'arrow-up-right': IconArrowUpRight,
    blocker: IconBlocker,
    calendar: IconCalendar,
    'calendar-check': IconCalendarCheck,
    'calendar-clock': IconCalendarClock,
    'calendar-range': IconCalendarRange,
    camera: IconCamera,
    check: IconCheck,
    'check-circle': IconCheckCircle,
    checklist: IconChecklist,
    'chevron-down': IconChevronDown,
    'chevron-left': IconChevronLeft,
    'chevron-right': IconChevronRight,
    'circle-dashed': IconCircleDashed,
    'circle-dot': IconCircleDot,
    clock: IconClock,
    close: IconClose,
    'close-circle': IconCloseCircle,
    dashboard: IconDashboard,
    edit: IconEdit,
    'exit-fullscreen': IconExitFullscreen,
    eye: IconEye,
    flame: IconFlame,
    forbidden: IconForbidden,
    fullscreen: IconFullscreen,
    history: IconHistory,
    hourglass: IconHourglass,
    image: IconImage,
    info: IconInfo,
    integration: IconIntegration,
    journal: IconJournal,
    key: IconKey,
    lock: IconLock,
    logout: IconLogout,
    menu: IconMenu,
    metrics: IconMetrics,
    more: IconMore,
    offline: IconOffline,
    palette: IconPalette,
    quote: IconQuote,
    report: IconReport,
    retry: IconRetry,
    save: IconSave,
    search: IconSearch,
    'server-error': IconServerError,
    shuffle: IconShuffle,
    spinner: IconSpinner,
    star: IconStar,
    'star-filled': IconStarFilled,
    sun: IconSun,
    tag: IconTag,
    target: IconTarget,
    tasks: IconTasks,
    trash: IconTrash,
    undo: IconUndo,
    upload: IconUpload,
    user: IconUser,
    warning: IconWarning,
} as const;

export type IconName = keyof typeof icons;
