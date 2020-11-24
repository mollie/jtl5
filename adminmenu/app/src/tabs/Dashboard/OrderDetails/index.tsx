import React, {useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import Alert from "@webstollen/react-jtl-plugin/lib/components/Alert";
import {faExclamation} from "@fortawesome/pro-solid-svg-icons";
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {ApiError} from "../../../helper";

export type OrderDetailsProps = {
    id: string
    onClose?: (event: React.MouseEvent<HTMLAnchorElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {

    const [data, setData] = useState<null | Record<string, any>>(null);
    const [error, setError] = useState<ApiError | null>(null);
    const [loading, setLoading] = useState(false);
    const api = useApi();

    useEffect(() => {
        setError(null);
        setData(null);
        if (props.id) {
            setLoading(true);
            api.run("orders", "one", {
                id: props.id
            })
                .then(res => {
                    setData(res.data);
                    setError(null)
                })
                .catch(setError)
                .finally(() => setLoading(false));
        }
    }, [props.id]);

    if (error !== null) {
        return <Alert variant={"error"} icon={{icon: faExclamation}}>Fehler beim laden der Bestellung
            "{props.id}": {error.message}</Alert>;
    }

    return <div className="relative flex-col mb-3">
        <Loading loading={loading}>
            <div className="flex-row bg-gray-300 rounded-t p-3">
                <div className=" font-bold text-2xl flex-grow">
                    Bestellung: <pre className="inline">{props.id}</pre>
                </div>
                <a className="font-bold text-2xl" onClick={props.onClose}>X</a>
            </div>
            <div className="bg-white rounded-b">
                ORDER DETAILS for {props.id}
                <pre style={{overflow: "scroll", maxHeight: "250px"}}>{JSON.stringify(data, null, 2)}</pre>
            </div>
        </Loading>
    </div>;
}

export default OrderDetails;