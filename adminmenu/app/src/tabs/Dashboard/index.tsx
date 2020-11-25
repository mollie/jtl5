import React, {useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import {PaymentMethod2img} from "../../helper";

const Dashboard = () => {

    const [methods, setMethods] = useState<Record<string, Record<string, any>>>({});

    const api = useApi();

    useEffect(() => {
        api.run('mollie', 'methods')
            .then(res => setMethods(res.data))
            .catch(console.error);
    }, [])

    return <div className="container mx-auto">
        <div className="m-1 w-full bg-white rounded-md p-4">
            <div className="flex items-center my-3">
                <img src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg" className="mr-2"
                     style={{maxWidth: '100px'}}/>
                <div className="text-xl">Integireren Sie alle wichtigen<br/>Zahlungsmethoden in kürzester zeit.</div>
            </div>

            {Object.keys(methods).length > 0 && <>
                <b>Derzeit mit Mollie angebunden:</b>
                <div className="flex flex-wrap content-center justify-start flex-row">
                    {Object.keys(methods).map(id => methods[id].shipping.length ?
                        <div key={id} className="" style={{flexBasis: '33%'}}>
                            <div className="m-2 p-2">
                                <PaymentMethod2img method={id}/> {methods[id].mollie.description}
                            </div>
                        </div> : null)}
                </div>
            </>}
        </div>

        {/*<pre>
            {JSON.stringify(methods, null, 2)}
        </pre>*/}
    </div>
}

export default Dashboard;