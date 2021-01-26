import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";

type LogsProps = {
    data: Array<Record<string, any>>
}

const Logs = ({data}: LogsProps) => {

    const template = {
        kId: {
            header: () => 'ID',
            data: row => row.kId
        },
        cType: {
            header: () => 'Typ',
            data: row => row.cType
        },
        cResult: {
            header: () => 'Result',
            data: row => row.cResult ?? 'n/a'
        },
        dDone: {
            header: () => 'Status',
            data: row => !row.dDone ? 'PENDING' : 'DONE',
            align: 'center'
        },
        dCreated: {
            header: () => 'Erstellt',
            data: row => row.dCreated,
            align: 'right'
        }
    } as Record<string, ItemTemplate<Record<string, any>>>;

    return <div>
        {data.length ? <Table template={template} items={data}/> : <div>Keine Daten</div>}
    </div>;
}

export default Logs;