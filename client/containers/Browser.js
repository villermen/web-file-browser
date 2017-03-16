// @flow
import { connect } from 'react-redux';
import Browser from '../components/Browser';
import { loadItemsIfNeeded } from '../actions/items';

type Props = {
    match: {
        params: {
            path: string,
        },
    },
};

function mapStateToProps({ items }) {
    return {
        loading: items.loading,
        items: items.items,
    };
}

function mapDispatchToProps(dispatch, props: Props) {
    dispatch(loadItemsIfNeeded(props.match.params.path || ''));

    return {};
}

export default connect(mapStateToProps, mapDispatchToProps)(Browser);
