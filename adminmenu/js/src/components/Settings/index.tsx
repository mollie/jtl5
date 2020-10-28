import React, {useState} from 'react';
import cls from './Settings.module.scss'
import $ from 'jquery'
import Loading from '../Loading';

type Props = {
    onClose: CallableFunction
    pluginInfo: Record<string, any>
}

const Settings = (props: Props) => {

    const settingsForm = document.querySelector('.settings-content form');
    const [loading, setLoading] = useState(false);

    const handleBackdrop = (e: React.MouseEvent) => {
        props.onClose();
    }

    const handleSubmit = (e: React.FormEvent) => {
        setLoading(true);
        const data = $(e.target).serialize()
        fetch('plugin.php?kPlugin=' + props.pluginInfo.id, {
            body: data,
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            method: 'POST'
        }).then((res) => {
            res.text().then(text => {
                $('.settings-content form').replaceWith($('.settings-content form', text));
                setLoading(false);
                props.onClose();
            })
        }).catch(e => {
            setLoading(false);
        })
        e.preventDefault();
    }

    return settingsForm?.outerHTML ? <div className={cls.settingsWrapper} onClick={handleBackdrop}>
        <Loading loading={loading} className={cls.modal} onClick={(e: React.MouseEvent) => e.stopPropagation()}>
            <form method='POST' onSubmit={handleSubmit} className={'navbar-form'}>
                <div dangerouslySetInnerHTML={{__html: settingsForm.innerHTML}}/>
            </form>
        </Loading>
    </div> : null;

}

export default Settings;