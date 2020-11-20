import React, {useState} from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {useApi} from "@webstollen/react-jtl-plugin/lib";

type MollieOrder = {};

const Dashboard = () => {

    const [maxItems, setMaxItems] = useState(0);
    const [loading, setLoading] = useState(false);
    const api = useApi();

    const template = {
        id: {
            header: () => 'ID',
            data: row => '1'
        } as ItemTemplate<MollieOrder>
    };

    const handleOnData = (page: number, itemsPerPage: number) => {
        setLoading(true);

        if (!maxItems) {
            api.select('SELECT count(o.kId) as maxItems FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung;').then(response => {
                setMaxItems(parseInt(response.maxItems));
            });
        }

        return new Promise<MollieOrder[]>((resolve, reject) => {
            api.selectAll("SELECT o.kId as maxItems, o.cOrderId, o.bTest, o.fAmountRefunded, o.fAmount, o.cCurrency, o.cLocale, o.cMethod, b.cBestellNr, b.cStatus, o.dCreated FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung LIMIT :offset, :limit;", {
                ':limit': itemsPerPage,
                ':offset': page * itemsPerPage
            })
                .then(resolve)
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