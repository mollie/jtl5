export const STATIC_URL = 'https://cdn.webstollen.com';

export const jtlStatus2text = (status: any) => {
    switch (parseInt(status)) {
        case -1:
            return 'storno';
        case 1:
            return 'offen';
        case 2:
            return 'in bearbeitung';
        case 3:
            return 'bezahlt';
        case 4:
            return 'versandt';
        case 5:
            return 'teilversandt';
        default:
            return 'n/a';
    }
}