import React, {useEffect, useState} from 'react';
import useAPI from "../../src/hooks/useAPI";
import DataTable, {createTheme, IDataTableColumn} from "react-data-table-component";
import {Order, OrderDetails} from "../types";
import moment from "moment";
import Loading from "../../src/components/Loading";
import {jtlStatus2text} from "../../src/utils";


const Orders = () => {

    createTheme('xxx', {background: {default: 'transparent'}})

    const [pagination, setPagination] = useState({page: 0, rpp: 10})

    const [data, setData] = useState<Order[]>([]);
    const [order, setOrder] = useState<OrderDetails | null>(null)
    const api = useAPI();
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        load()
    }, []);

    const handleClick = (row: Order) => {
        setLoading(true);
        api.run('Orders', 'one', {id: row.kId})
            .then(res => {
                setOrder(res.data);
                setLoading(false);
            })
            .catch(alert)
    }
    const handleChangePage = (page:number, totalRows:number) => {}
    const handleChangeRowsPerPage = (currentRowsPerPage:number, currentPage:number) => {}

    if (order) {
        return <div className={'w-full rounded-xl bg-white relative'}>
            <Loading loading={loading}>
                <a onClick={()=>setOrder(null)} href={'#'}>schließen</a>
                <pre>
                    {JSON.stringify(order, null, 2)}
                </pre>

            </Loading>
        </div>;
    }

    const load = () => {
        setLoading(true);
        api.run("Orders", 'all')
            .then(res => {
                setData(res.data)
                setLoading(false)
            })
            .catch(alert);
    }

    const cols = [
        {
            name: 'BestellNr.',
            selector: 'cBestellNr',
            sortable: true,
        },
        {
            name: 'mollie ID',
            selector: 'cOrderId',
            sortable: true,
        },
        {
            name: 'Modus',
            selector: 'bTest',
            sortable: true,
            cell: row => <b>{row.bTest ? 'TEST' : 'LIVE'}</b>
        },
        {
            name: 'mollie Status',
            selector: 'cStatus',
            sortable: true,
        },
        {
            name: 'JTL Status',
            selector: 'cJTLStatus',
            sortable: true,
            format: row => jtlStatus2text(row.cJTLStatus)
        },
        {
            name: 'Betrag',
            selector: 'fGesamtsumme',
            sortable: true,
        },
        {
            name: 'Erstellt',
            selector: 'dCreated',
            sortable: true,
            format: row => moment(row.dCreated).format('HH:mm:ss DD.MM.YYYY')
        }
    ] as IDataTableColumn[];

    return <div className={'w-full rounded-xl bg-white relative'}>
        <Loading loading={loading}>
            <DataTable columns={cols} data={data}
                       title={'Bestellungen'}
                       striped={true}
                       highlightOnHover={true}
                       pagination={true}
                       theme={'xxx'}
                       onRowClicked={handleClick}
                       onChangePage={handleChangePage}
                       onChangeRowsPerPage={handleChangeRowsPerPage}
                       paginationServer={true}
                       paginationPerPage={pagination.rpp}

            />
        </Loading>
    </div>;
}

export default React.memo(Orders);