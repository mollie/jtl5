import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";

export type ShipmentsProps = {
    mollie: Record<string, any>
}

const Shipments = ({mollie}: ShipmentsProps) => {

    if (!mollie._embedded.shipments) {
        return <p>No shipments!</p>;
    }

    const template = {
        id: {
            header: () => 'ID',
            data: row => row.id
        },
        carrier: {
            header: () => 'Carrier',
            data: row => row.tracking ? row.tracking.carrier : 'n/a'
        },
        code: {
            header: () => 'Code',
            data: row => row.tracking ? row.tracking.code : 'n/a'
        },
        actions: {
            header: () => ' ',
            data: row => row.tracking?.url ? <TextLink href={row.tracking.url}>O</TextLink> : '-'
        },
    } as Record<string, ItemTemplate<Record<string, any>>>

    return <Table striped template={template} items={mollie._embedded.shipments}/>;
}

export default Shipments;