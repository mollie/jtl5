import React, {useState} from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {formatAmount, Label} from "@webstollen/react-jtl-plugin/lib";
import {jtlStatus2label, MollieOrder, molliePaymentStatusLabel, PaymentMethod2img} from "../../helper";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import OrderDetails from "./OrderDetails";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";


const Dashboard = () => {

    const [maxItems, setMaxItems] = useState(0);
    const [loading, setLoading] = useState(false);
    const api = useApi();

    const [detailId, setDetailId] = useState<string | null>(null)

    const template = {
        cBestellNr: {
            header: () => 'BestellNr',
            data: row => <TextLink color={"blue"} onClick={() => setDetailId(row.cOrderId)}>{row.cBestellNr}</TextLink>
        },
        cOrderId: {
            header: () => 'mollie ID',
            data: row => <>
                {row.bTest ? <Label className={'inline mr-1'} color={"red"}>TEST</Label> : null}
                <pre className={'inline'}>{row.cOrderId}</pre>
            </>
        },
        cStatus: {
            header: () => 'mollie Status',
            data: row => molliePaymentStatusLabel(row.cStatus),
            align: "center"
        },
        cJTLStatus: {
            header: () => 'JTL Status',
            data: row => jtlStatus2label(row.cJTLStatus),
            align: "center"
        },
        cLocale: {
            header: () => 'Locale',
            data: row => row.cLocale
        },
        cMethod: {
            header: () => 'Methode',
            data: row => <PaymentMethod2img method={row.cMethod}/>,
            align: "center"
        },
        fAmount: {
            header: () => 'Betrag',
            data: row => formatAmount(row.fAmount),
            align: "right"
        },
        cCurrency: {
            header: () => 'Währung',
            data: row => row.cCurrency
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
            api.selectAll("SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung ORDER BY b.dErstellt DESC;", {
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

    return <div className='container mx-auto relative'>
        {detailId && <OrderDetails onClose={() => setDetailId(null)} id={detailId}/>}
        {/*<div className={detailId ? 'hidden' : undefined}>*/}
        <Table striped template={template}
               loading={loading}
               onData={handleOnData}
               paginate={{
                   maxPages: 2,
                   maxItems: maxItems,
                   itemsPerPage: 10
               }}/>
        {/*</div>*/}
    </div>;
};

export default Dashboard;