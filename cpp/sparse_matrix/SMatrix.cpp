#include "SMatrix.h"
using namespace std;
// Stephan Ohlsson
// z3389772 
// stephan.ohlsson@gmail.com
// Just a note, I am keeping entries in cidx_ ordered (sorted), with respect to row
// It makes comparison and printing a breeze, and also allows me to use std::find (binary search)
// when searching a range of cidx_ for specific columns

std::ostream& operator<<(std::ostream &os, const SMatrix &m) {
	cout << "(" << m.rows() << "," << m.cols() << "," << m.numVals << ")" << endl;
	// iterate over map
	map<size_t, pair<size_t, unsigned int> >::const_iterator itr = m.ridx_.begin();
	while(itr != m.ridx_.end()) {
		size_t index = (*itr).second.first;
		unsigned int numValues = (*itr).second.second;
		unsigned int i = 0;
		while(i < numValues) {
			cout << "(" << (*itr).first << "," << m.cidx_[index] << "," << m.vals_[index] << ")";
			i++;
			if(i < numValues)
			cout << " ";
			index++;
		}
		itr++;
		if(itr != m.ridx_.end())
		cout << endl;
	}
	return os;
}
SMatrix::SMatrix(size_type r, size_type c) {
	numRows = r;
	numCols = c;
	size_type array_length = (r * c) / 5;
	if(1000 < array_length) 
	array_length = 1000;
	vals_ = new int[array_length];
	cidx_ = new size_type[array_length];
	numVals = 0;
	alloc_size = array_length;
}
SMatrix::SMatrix(const SMatrix& m) {
	numRows = m.numRows;
	numCols = m.numCols;
	numVals = m.numVals;
	alloc_size = m.alloc_size;
	itrRow = m.itrRow;
	itrCol = m.itrCol;
	ridx_ = m.ridx_;
	col_ = m.col_;
	vals_ = new int[alloc_size];
	cidx_ = new size_type[alloc_size];
	for(size_t i = 0; i < numVals; i++) {
		vals_[i] = m.vals_[i];
		cidx_[i] = m.cidx_[i];
	}
}
SMatrix::SMatrix(std::istream& is) {
	// Using C tokenizing here because it seems easiest...
	char *in;
	char tmp[100];
	char c = ')';
	is.getline(tmp, 100, c);
	in = strtok(tmp, " ,()");
	numRows = atoi(in);
	in = strtok(NULL, " ,()");
	numCols = atoi(in);
	in = strtok(NULL," ,()");
	numVals = atoi(in);
	alloc_size = numVals * 2;
	vals_ = new int[alloc_size];
	cidx_ = new size_type[alloc_size];
	// Have to reset numvals because setVal increments it
	numVals = 0;
	while(!is.getline(tmp, 100, c).eof()) {
		in = strtok(tmp," ,()\n");
		size_t row = atoi(in);
		in = strtok(NULL," ,()");
		size_t col = atoi(in);
		in = strtok(NULL," ,()");
		unsigned int val = atoi(in);
		setVal(row,col,val);
	}
}
SMatrix::~SMatrix() {
	delete[] vals_;
	vals_ = 0;
	delete[] cidx_;
	cidx_ = 0;
}
void SMatrix::begin() const {
	itrRow = 0;
	itrCol = 0;
}
bool SMatrix::end() const {
	return (itrRow == numRows);
}
void SMatrix::next() const {
	if(++itrCol == numCols) {
		itrRow++;
		itrCol = 0;
	}
}
int SMatrix::value() const {
	return (*this)(itrRow, itrCol);
}
SMatrix& SMatrix::operator=(const SMatrix& m) {
	numRows = m.numRows;
	numCols = m.numCols;
	numVals = m.numVals;
	alloc_size = m.alloc_size;
	itrRow = m.itrRow;
	itrCol = m.itrCol;
	ridx_ = m.ridx_;
	col_ = m.col_;
	delete[] vals_;
	delete[] cidx_;
	vals_ = new int[alloc_size];
	cidx_ = new size_type[alloc_size];
	for(size_t i = 0; i < numVals; i++) {
		vals_[i] = m.vals_[i];
		cidx_[i] = m.cidx_[i];
	}
	return *this;
}
SMatrix& SMatrix::operator+=(const SMatrix& m) throw(MatrixError) {
	if(numRows != m.rows() || numCols != m.cols())
	throw (MatrixError("Matrix size error"));

	map< size_type, std::pair<size_t, unsigned int> >::const_iterator itr = ridx_.begin();
	size_type row, index;
	unsigned int num_entries;
	while(itr != ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		int val = 0;
		size_type k;
		for(k=index; k < index+num_entries; k++) {
			val = vals_[k] + m(row,cidx_[k]);
			this->setVal(row,cidx_[k], val);
			// If val is 0, then we are deleting entries out of cidx, so we have to
			// stay in the same index or else we'll skip over entries
			if(val == 0) {
				num_entries--;
				k--;
			}
		}
		itr++;
	}
	return *this;
	
}
SMatrix& SMatrix::operator-=(const SMatrix& m) throw(MatrixError) {
	if(numRows != m.rows() || numCols != m.cols())
	throw (MatrixError("Matrix size error"));
	
	map< size_type, std::pair<size_t, unsigned int> >::const_iterator itr = ridx_.begin();
	size_type row, index;
	unsigned int num_entries;
	while(itr != ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		int val = 0;
		size_type k;
		for(k=index; k < index+num_entries; k++) {
			val = vals_[k] - m(row,cidx_[k]);
			this->setVal(row,cidx_[k], val);
			// If val is 0, then we are deleting entries out of cidx, so we have to 
			// stay in the same index or else we'll skip over entries
			if(val == 0) {
				num_entries--;
				k--;
			}
		}
		itr++;
	}
	return *this;        
}
SMatrix& SMatrix::operator*=(const SMatrix& m) throw(MatrixError) {
	if(numCols != m.rows())
	throw (MatrixError("Matrix size error"));
	
	SMatrix r(numRows, m.cols());
	map< size_type, std::pair<size_t, unsigned int> >::const_iterator itr = ridx_.begin();
	size_type row, index;
	unsigned int num_entries;
	while(itr != ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		// Iterate over columns that actually have values in them (col_ is a set)
		for(set<size_type>::iterator c_itr = m.col_.begin(); c_itr != m.col_.end(); c_itr++) {
			int val = 0;
			size_type k;
			for(k=index; k < index+num_entries; k++) {
				val += vals_[k] * m(cidx_[k],*c_itr);
			}
			r.setVal(row,*c_itr,val);
			k = index;
		}
		itr++;
	}
	*this = r;
	return *this;
}
int SMatrix::operator()(size_type r, size_type c) const throw(MatrixError) {
	if(r > numRows || c > numCols) {
		throw (MatrixError("Matrix bound error"));
	}
	map< size_type, std::pair<size_t, unsigned int> >::const_iterator itr = ridx_.find(r);
	if(itr == ridx_.end()) {
		return 0;
	}
	
	size_type *begin = cidx_ + (*itr).second.first;
	size_type *end =  cidx_ + ((*itr).second.first + (*itr).second.second);
	size_type *itr2 = std::find(begin, end, c);
	if(itr2 == end)
	return 0;
	else {
		size_type t = itr2 - begin;
		return vals_[(*itr).second.first+ t];
	}
}
bool operator==(const SMatrix& a, const SMatrix& b) {
	// Since I preserve column ordering, we can just scan 
	// ridx_, cidx_ and vals_ and see if there are equal
	if(a.ridx_ != b.ridx_)
	return false;
	if(a.numVals != b.numVals)
	return false;
	for(size_t i = 0; i < a.numVals; i++) {
		if(a.cidx_[i] != b.cidx_[i])
		return false;
		if(a.vals_[i] != b.vals_[i])
		return false;
	}
	return true;
}
bool operator!=(const SMatrix& a, const SMatrix& b) {
	// Since I preserve column ordering, we can just scan ridx_, 
	// cidx_ and vals_ and see if there are equal
	if(a.ridx_ != b.ridx_)
	return true;
	if(a.numVals != b.numVals)
	return true;
	for(size_t i = 0; i < a.numVals; i++) {
		if(a.cidx_[i] != b.cidx_[i])
		return true;
		if(a.vals_[i] != b.vals_[i])
		return true;
	}
	return false;
}
SMatrix operator+(const SMatrix& a, const SMatrix& b) throw(MatrixError) {
	if(a.rows() != b.rows() || a.cols() != b.cols())
	throw (MatrixError("Matrix size error"));
	
	SMatrix c(a.rows(),a.cols());
	map< size_t, std::pair<size_t, unsigned int> >::const_iterator itr = a.ridx_.begin();
	size_t row, index;
	unsigned int num_entries;
	while(itr != a.ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		int val = 0;
		size_t k;
		for(k=index; k < index+num_entries; k++) {
			val = a.vals_[k] + b(row,a.cidx_[k]);
			c.setVal(row,a.cidx_[k], val);
		}
		itr++;
	}
	return c;

}
SMatrix operator-(const SMatrix& a, const SMatrix& b) throw(MatrixError) {
	if(a.rows() != b.rows() || a.cols() != b.cols())
	throw (MatrixError("Matrix size error"));
	
	SMatrix c(a.rows(),a.cols());
	map< size_t, std::pair<size_t, unsigned int> >::const_iterator itr = a.ridx_.begin();
	size_t row, index;
	unsigned int num_entries;
	while(itr != a.ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		int val = 0;
		size_t k;
		for(k=index; k < index+num_entries; k++) {
			val = a.vals_[k] - b(row,a.cidx_[k]);
			c.setVal(row,a.cidx_[k], val);
		}
		itr++;
	}
	return c;
}
SMatrix operator*(const SMatrix& a, const SMatrix& b) throw(MatrixError) {
	if(a.cols() != b.rows())
	throw (MatrixError("Matrix size error"));

	SMatrix r(a.rows(), b.cols());
	map< size_t, std::pair<size_t, unsigned int> >::const_iterator itr = a.ridx_.begin();
	size_t row, index;
	unsigned int num_entries;
	while(itr != a.ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		// Iterate over columns that actually have values in them
		for(set<size_t>::iterator c_itr = b.col_.begin(); c_itr != b.col_.end(); c_itr++) {
			int val = 0;
			size_t k;
			for(k=index; k < index+num_entries; k++) {
				val += a.vals_[k] * b(a.cidx_[k],*c_itr);
			}
			r.setVal(row,*c_itr,val);
			k = index;
		}
		itr++;
	}
	return r;
}
SMatrix transpose(const SMatrix& a) {
	SMatrix r(a.cols(), a.rows());
	map< size_t, std::pair<size_t, unsigned int> >::const_iterator itr = a.ridx_.begin();
	size_t row, index;
	unsigned int num_entries;
	while(itr != a.ridx_.end()) {
		row = (*itr).first;
		index = (*itr).second.first;
		num_entries = (*itr).second.second;
		for(size_t k = index; k < index+num_entries; k++) {
			r.setVal(a.cidx_[k],row,a.vals_[k]);
		}
		itr++;
	}
	return r;

}
void SMatrix::addRows(size_type n, size_type m) throw(MatrixError) {
	if(n > numRows || m > numRows)
	throw (MatrixError("Matrix bound error"));

	if(ridx_.find(m) == ridx_.end())
	return;
	if(ridx_.find(n) == ridx_.end()) {
		// Nasty
	}
	else {	
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrm = ridx_.find(m);
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrn = ridx_.find(n);
		size_type m_index = (*itrm).second.first;
		unsigned int m_num_entries = (*itrm).second.second;
		size_type m_upperBound = m_index + m_num_entries;
		size_type n_index = (*itrn).second.first;
		unsigned int n_num_entries = (*itrn).second.second;
		size_type n_upperBound = n_index + n_num_entries;
		int *tempvals = new int[n_num_entries + m_num_entries];
		size_type *tempcidx = new size_type[n_num_entries + m_num_entries];
		size_type t = 0;
		// Load each row into a temp array, adding them together
		while(m_index < m_upperBound || n_index < n_upperBound) {
			if(m_index == m_upperBound) {
				tempvals[t] = vals_[n_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
			}
			else if(n_index == n_upperBound) {
				tempvals[t] = vals_[m_index];
				tempcidx[t] = cidx_[m_index];
				m_index++;
			}
			else if(cidx_[n_index] < cidx_[m_index]) {
				tempvals[t] = vals_[n_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
			}
			else if(cidx_[n_index] == cidx_[m_index]) {
				tempvals[t] = vals_[n_index] + vals_[m_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
				m_index++;
			}
			else {
				tempvals[t] = vals_[m_index];
				tempcidx[t] = cidx_[m_index];
				m_index++;
			}
			t++;
		}
		n_index = (*itrn).second.first;
		size_type numToShift = t - n_num_entries;
		numVals += numToShift;
		// Realloc if needed
		if(numVals >= alloc_size -1)
		alloc();
		// Shift all values to account for new entries
		for(size_type i = numVals - 1; i > n_index; i--) {
			cidx_[i] = cidx_[i-numToShift];
			vals_[i] = vals_[i-numToShift];
		}
		// Load the new values in
		for(size_type i = n_index, j = 0; j < t; i++, j++) {
			cidx_[i] = tempcidx[j];
			vals_[i] = tempvals[j];
		}
		// Update ridx_
		(*itrn).second.second += numToShift;
		itrn++;
		while(itrn != ridx_.end()) {
			(*itrn).second.first += numToShift;
			itrn++;
		}
		delete [] tempvals;
		delete [] tempcidx;
	}

}
void SMatrix::subRows(size_type n, size_type m) throw(MatrixError) {
	if(n > numRows || m > numRows)
	throw (MatrixError("Matrix bound error"));

	if(ridx_.find(m) == ridx_.end())
	return;
	else {
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrm = ridx_.find(m);
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrn = ridx_.find(n);
		size_type m_index = (*itrm).second.first;
		unsigned int m_num_entries = (*itrm).second.second;
		size_type m_upperBound = m_index + m_num_entries;
		size_type n_index = (*itrn).second.first;
		unsigned int n_num_entries = (*itrn).second.second;
		size_type n_upperBound = n_index + n_num_entries;
		int *tempvals = new int[n_num_entries + m_num_entries];
		size_type *tempcidx = new size_type[n_num_entries + m_num_entries];
		size_type t = 0;
		// Combine rows into temp array
		while(m_index < m_upperBound || n_index < n_upperBound) {
			if(m_index == m_upperBound) {
				tempvals[t] = vals_[n_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
			}
			else if(n_index == n_upperBound) {
				tempvals[t] = vals_[m_index] * -1;
				tempcidx[t] = cidx_[m_index];
				m_index++;
			}
			else if(cidx_[n_index] < cidx_[m_index]) {
				tempvals[t] = vals_[n_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
			}
			else if(cidx_[n_index] == cidx_[m_index]) {
				tempvals[t] = vals_[n_index] - vals_[m_index];
				tempcidx[t] = cidx_[n_index];
				n_index++;
				m_index++;
			}
			else {
				tempvals[t] = vals_[m_index] * -1;
				tempcidx[t] = cidx_[m_index];
				m_index++;
			}
			t++;
		}
		n_index = (*itrn).second.first;
		size_type numToShift = t - n_num_entries;
		numVals += numToShift;
		if(numVals >= alloc_size -1)
		alloc();
		// Shift to account for new entries
		for(size_type i = numVals - 1; i > n_index; i--) {
			cidx_[i] = cidx_[i-numToShift];
			vals_[i] = vals_[i-numToShift];
		}
		// Load new entries
		for(size_type i = n_index, j = 0; j < t; i++, j++) {
			cidx_[i] = tempcidx[j];
			vals_[i] = tempvals[j];
		}
		// Update ridx_
		(*itrn).second.second += numToShift;
		itrn++;
		while(itrn != ridx_.end()) {
			(*itrn).second.first += numToShift;
			itrn++;
		}
		delete [] tempvals;
		delete [] tempcidx;
	}
}
void SMatrix::addCols(size_type n, size_type m) throw(MatrixError) {
	if(n > numCols || m > numCols)
	throw (MatrixError("Matrix bound error"));

	SMatrix r = transpose(*this);
	r.addRows(n, m);
	r = transpose(r);
	*this = r;
}
void SMatrix::subCols(size_type n, size_type m) throw(MatrixError) {
	if(n > numCols || m > numCols)
	throw (MatrixError("Matrix bound error"));

	SMatrix r = transpose(*this);
	r.subRows(n, m);
	r = transpose(r);
	*this = r;
}
void SMatrix::swapRows(size_type n, size_type m) throw(MatrixError) {
	if(n > numRows || m > numRows)
	throw (MatrixError("Matrix bound error"));

	if(ridx_.find(m) == ridx_.end())
	return;
	if(ridx_.find(n) == ridx_.end()) {
		// Nasty
	}
	else {
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrm = ridx_.find(m);
		map< size_type, std::pair<size_t, unsigned int> >::iterator itrn = ridx_.find(n);
		size_type m_index = (*itrm).second.first;
		unsigned int m_num_entries = (*itrm).second.second;
		size_type n_index = (*itrn).second.first;
		unsigned int n_num_entries = (*itrn).second.second;
		int *tempvals_n = new int[n_num_entries];
		size_type *tempcidx_n = new size_type[n_num_entries];
		int *tempvals_m = new int[m_num_entries];
		size_type *tempcidx_m = new size_type[m_num_entries];
		// Load each row into its own temp array
		for(size_type i = (*itrm).second.first, j = 0; i < (*itrm).second.first + (*itrm).second.second; i++, j++) {
			tempcidx_m[j] = cidx_[i];
			tempvals_m[j] = vals_[i];
		}
		for(size_type i = (*itrn).second.first, j = 0; i < (*itrn).second.first + (*itrn).second.second; i++, j++) {
			tempcidx_n[j] = cidx_[i];
			tempvals_n[j] = vals_[i];
		}
		// Resize arrays appropriately
		if(m_num_entries > n_num_entries) {
			shift_left(m,m_num_entries - n_num_entries);
			shift_right(n, m_num_entries - n_num_entries);
		}
		else if(m_num_entries < n_num_entries) {
			shift_left(n, n_num_entries - m_num_entries);
			shift_right(m, n_num_entries - m_num_entries);
		}
		n_index = (*itrn).second.first;
		m_index = (*itrm).second.first;
		// Now copy, update itrs
		for(size_type i = m_index, j = 0; j < n_num_entries; j++, i++) {
			vals_[i] = tempvals_n[j];
			cidx_[i] = tempcidx_n[j];
		}
		for(size_type i = n_index, j = 0; j < m_num_entries; j++, i++) {
			vals_[i] = tempvals_m[j];
			cidx_[i] = tempcidx_m[j];
		}
		(*itrm).second.second = n_num_entries;
		(*itrn).second.second = m_num_entries;

	}
}
void SMatrix::swapCols(size_type n, size_type m) throw(MatrixError) {
	if(n > numCols || m > numCols)
	throw (MatrixError("Matrix bound error"));

	SMatrix r = transpose(*this);
	r.swapRows(n, m);
	r = transpose(r);
	*this = r;
}
SMatrix SMatrix::identity(size_type n) {
	SMatrix r(n,n);
	// Since we aren't using insert(), we should handle memory explicitly
	delete[] r.vals_;
	delete [] r.cidx_;
	r.vals_ = new int[n];
	r.cidx_ = new size_type[n];
	r.alloc_size = n;
	r.numVals = n;
	for(size_type i = 0; i < n; i++) {
		r.vals_[i] = 1;	
		r.cidx_[i] = i;
		r.ridx_[i] = make_pair(i,1);
		r.col_.insert(i);
	}
	return r;
}
bool SMatrix::setVal(size_type row, size_type col, int value) throw(MatrixError) {
	bool b_alloc = false;
	// Check for bound error
	if(row > numRows || col > numCols) {
		throw (MatrixError("Matrix bound error"));
	}
	// Check if we need to alloc additional memory
	if(numVals == alloc_size - 1) {
		alloc();
		b_alloc = true;
	}
	// Special 0 condition
	if(value == 0) {
		if(ridx_.count(row) != 0)
		insertZero(row, col);
	}
	// Empty condition
	else if(numVals == 0) {
		vals_[0] = value;
		cidx_[0] = col;
		ridx_[row] = make_pair(0,1);
		numVals++;
	}
	// Insert a new row
	else if(ridx_.count(row) == 0) {
		insertNewRow(row, col, value);
	}
	// Row exists, update row and shift cidx_/vals_ right, keep column ordered
	else {
		insertExistingRow(row, col, value);
	}
	// Keep track of used columns (used for multiplication)
	col_.insert(col);
	return b_alloc;
}

void SMatrix::insertNewRow(size_type row, size_type col, int value) {
	map<size_type, pair<size_t, unsigned int> >::iterator itr = ridx_.upper_bound(row);
	if(itr == ridx_.end()) {
		vals_[numVals] = value;
		cidx_[numVals] = col;
		ridx_[row] = make_pair(numVals, 1);
		numVals++;
	}
	else {
		size_t index = (*itr).second.first;
		while(itr != ridx_.end()) {
			(*itr).second.first++;
			itr++;
		}
		//numVals++;
		for(size_type i = numVals; i > index; i--) {
			vals_[i] = vals_[i-1];
			cidx_[i] = cidx_[i-1];
		}
		numVals++;
		vals_[index] = value;
		cidx_[index] = col;
		ridx_[row] = make_pair(index, 1);
	}
}
void SMatrix::insertExistingRow(size_type row, size_type col, int value) {
	map<size_type, pair<size_t, unsigned int> >::iterator itr = ridx_.find(row);
	size_type *begin = cidx_ + (*itr).second.first;
	size_type *end =  cidx_ + ((*itr).second.first + (*itr).second.second);
	size_type *itr2 = std::lower_bound(begin, end, col);
	size_type index = (*itr).second.first;
	index += (itr2 - begin);

	
	// if index == numvals than this is going to be the last element in the array,
	// so we don't need to do any shifting (yay!)
	if(index == numVals) {
		vals_[numVals] = value;
		cidx_[numVals] = col;
		(*itr).second.second++;
		numVals++;
	}
	// Check if we are updating an existing value (no other changes neccessary)
	else if(cidx_[index] == col) {
		vals_[index] = value;
	}
	// Inserting a new value. Shift, preserving column order
	else {
		(*itr).second.second++;
		itr++;
		while(itr != ridx_.end()) {
			(*itr).second.first++;
			itr++;
		}
		//numVals++;
		for(size_type i = numVals; i > index; i--) {
			vals_[i] = vals_[i-1];
			cidx_[i] = cidx_[i-1];
		}
		vals_[index] = value;
		cidx_[index] = col;
		numVals++;
	}

}
void SMatrix::insertZero(size_type row, size_type col) {
	map<size_type, pair<size_t, unsigned int> >::iterator itr = ridx_.find(row);
	if(itr == ridx_.end())
	return;
	unsigned int numInRow = (*itr).second.second;
	size_type *begin = cidx_ + (*itr).second.first;
	size_type *end =  cidx_ + ((*itr).second.first + (*itr).second.second);
	size_type *itr2 = std::find(begin, end, col);
	size_type index = (*itr).second.first;
	index += (itr2 - begin);
	// They tried to set a 0 column to 0
	if(itr2 == end)
	return;
	// Shift left
	for(size_type i = index; i < numVals; i++) {
		cidx_[i] = cidx_[i+1];
		vals_[i] = vals_[i+1];
	}
	// Update ridx_
	(*itr).second.second--;
	numVals--;
	itr++;
	while(itr != ridx_.end()) {
		(*itr).second.first--;
		itr++;
	}
	if(numInRow == 1) {
		ridx_.erase(ridx_.find(row));
	}
}
void SMatrix::alloc() {
	alloc_size = alloc_size * 2;
	int *new_vals_ = new int[alloc_size];
	size_type *new_cidx_ = new size_type[alloc_size];
	for(size_type i = 0; i < numVals; i++) {
		new_vals_[i] = vals_[i];
		new_cidx_[i] = cidx_[i];
	}
	delete[] vals_;
	delete[] cidx_;
	vals_ = new_vals_;
	cidx_ = new_cidx_;
}
// Collapses cidx_ and vals_ corresponding to row by val, updating ridx_
// Note this destroys values in row (row-1). Used by swapRows()
void SMatrix::shift_left(size_type row, unsigned int val) {
	map<size_type, pair<size_t, unsigned int> >::iterator itr = ridx_.upper_bound(row);
	size_type index = (*itr).second.first;
	if(itr != ridx_.end()) {	
		for(size_type i = index; i < numVals; i++) {
			vals_[i-val] = vals_[i];
			cidx_[i-val] = cidx_[i];
		}
		while(itr != ridx_.end()) {
			(*itr).second.first -= val;
			itr++;
		}
	}
	numVals -= val;
}	
// Expands cidx_ and vals_
void SMatrix::shift_right(size_type row, unsigned int val) {
	map<size_type, pair<size_t, unsigned int> >::iterator itr = ridx_.upper_bound(row);
	size_type index = (*itr).second.first;
	numVals += val;
	if(itr != ridx_.end()) {
		for(size_type i = numVals -1; i >= index; i--) {
			vals_[i] = vals_[i-val];
			cidx_[i] = cidx_[i-val];
		}	
		while(itr != ridx_.end()) {
			(*itr).second.first += val;
			itr++;
		}
	}
}
