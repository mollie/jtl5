import React, {useState} from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {jtlStatus2text, useApi} from "@webstollen/react-jtl-plugin/lib";

type MollieOrder = {
    bSynced: boolean
    bTest: boolean
    cBestellNr: string
    cCurrency: string
    cHash: string
    cLocale: string
    cMethod: string
    cOrderId: string
    cStatus: string
    cThirdId: string
    cTransactionId: string
    dCreated: string
    fAmount: string
    fAmountRefunded: number
    kBestellung: number
    kId: number

    cJTLStatus: string
    cAbgeholt: string
    cVersandartName: string
    cZahlungsartName: string
    fGesamtsumme: number
    fGuthaben: number

    // cIP: string
    // cKommentar: string
    // cLogistiker: string
    // dBezahltDatum: string | null
    // kSprache: number
    // kVersandart: number
    // kZahlungsart: number
    // kWaehrung: number
    // kWarenkorb: number
};

const molliePaymentStatusLabel = (status: string) => {
    return <div className={status}>{status}</div>
}

const Dashboard = () => {

    const [maxItems, setMaxItems] = useState(0);
    const [loading, setLoading] = useState(false);
    const api = useApi();

    const template = {
        cBestellNr: {
            header: () => 'BestellNr',
            data: row => <>{row.cBestellNr}{row.bTest ? <div className='test'>TEST</div> : null}</>
        },
        cOrderId: {
            header: () => 'mollie ID',
            data: row => row.cOrderId
        },
        cStatus: {
            header: () => 'mollie Status',
            data: row => molliePaymentStatusLabel(row.cStatus),
        },
        cJTLStatus: {
            header: () => 'mollie Status',
            data: row => jtlStatus2text(row.cJTLStatus),
        },
        fAmount: {
            header: () => 'Betrag',
            data: row => parseFloat(row.fAmount).toFixed(2),
            align: "right"
        },
        cCurrency: {
            header: () => 'Währung',
            data: row => row.cCurrency
        },
        cLocale: {
            header: () => 'Locale',
            data: row => row.cLocale
        },
        cMethod: {
            header: () => 'Methode',
            data: row => row.cMethod
        },
        dCreated: {
            header: () => 'Erstellt',
            data: row => row.dCreated,
            align: "right"
        }
    } as Record<string, ItemTemplate<MollieOrder>>;

    const handleOnData = (page: number, itemsPerPage: number) => {
        setLoading(true);

        return new Promise<MollieOrder[]>((resolve, reject) => {
            api.selectAll("SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung;", {
                ':limit': itemsPerPage,
                ':offset': page * itemsPerPage
            })
                .then((response: Record<string, any>) => {
                    setMaxItems(response.maxItems);
                    resolve(response.items)
                })
                .catch(reject)
                .finally(() => setLoading(false));
        })
    };

    return <div className='container mx-auto'>
        <Table template={template}
               loading={loading}
               onData={handleOnData}
               paginate={{
                   maxPages: 2,
                   maxItems: maxItems,
                   itemsPerPage: 10
               }}/>
    </div>;
};

export default Dashboard;